<?php

namespace App\Http\Controllers;

use App\Models\Carro;
use App\Models\Funcionarios;
use App\Models\Ordem;
use App\Models\OrdemApi;
use App\Models\OrdemTecnico;
use App\Models\RoteiroTecnico;
use App\Models\RoteiroTecnicoItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RotasController extends Controller
{
    /**
     * Display the list of technical routes
     */
    public function index(Request $request)
    {
        $query = RoteiroTecnico::query()
            ->withCount('itens')
            ->selectSub(function ($subquery) {
                $subquery->from('Roteiros tecnicos itens')
                    ->selectRaw('MIN(TRY_CONVERT(datetime2, [Data visita], 103))')
                    ->whereRaw('[Roteiros tecnicos itens].[Numero roteiro] = [Roteiros tecnicos].[Numero roteiro]');
            }, 'data_visita_min');

        // Filtro Global (Search)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('Numero roteiro', 'like', '%'.$search.'%')
                    ->orWhere('Tecnico', 'like', '%'.$search.'%')
                    ->orWhere('Operador', 'like', '%'.$search.'%');
            });
        }

        // Filtro por número do roteiro
        if ($request->filled('numero_roteiro')) {
            $query->where('Numero roteiro', 'like', '%'.$request->numero_roteiro.'%');
        }

        // Filtro por técnico
        if ($request->filled('tecnico')) {
            $query->where('Tecnico', $request->tecnico);
        }

        // Filtro por operador
        if ($request->filled('operador')) {
            $query->where('Operador', $request->operador);
        }

        // Filtro por data de emissão (início)
        if ($request->filled('data_inicio')) {
            $query->whereDate('Data emissao', '>=', $request->data_inicio);
        }

        // Filtro por data de emissão (fim)
        if ($request->filled('data_fim')) {
            $query->whereDate('Data emissao', '<=', $request->data_fim);
        }

        // Filtro por dia da visita (busca nos itens)
        if ($request->filled('data_visita')) {
            $query->whereHas('itens', function ($q) use ($request) {
                $q->whereDate('Data visita', $request->data_visita);
            });
        }

        // Filtro por quantidade de OS
        if ($request->filled('qtd_os')) {
            $query->has('itens', '=', $request->qtd_os);
        }

        $roteiros = $query->with('itens');

        $this->applyRoteiroSorting($roteiros, $request);

        $roteiros = $roteiros->paginate(15);

        // Fetch unique technicians and operators for filters
        $tecnicosList = RoteiroTecnico::select('Tecnico')
            ->whereNotNull('Tecnico')
            ->distinct()
            ->orderBy('Tecnico')
            ->pluck('Tecnico');

        $operadoresList = RoteiroTecnico::select('Operador')
            ->whereNotNull('Operador')
            ->distinct()
            ->orderBy('Operador')
            ->pluck('Operador');

        return view('rotas.list', compact('roteiros', 'tecnicosList', 'operadoresList'));
    }

    private function applyRoteiroSorting($query, Request $request): void
    {
        $sortBy = (string) $request->input('sort_by', 'numero_roteiro');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortKey = in_array($sortBy, [
            'numero_roteiro',
            'data_emissao',
            'tecnico',
            'dia_visita',
            'operador',
            'qtd_os',
        ], true) ? $sortBy : 'numero_roteiro';

        $query->reorder();

        if ($request->filled('numero_roteiro')) {
            $query->orderByRaw("CASE WHEN [Numero roteiro] = ? THEN 0 ELSE 1 END", [$request->numero_roteiro]);
        }

        switch ($sortKey) {
            case 'data_emissao':
                $query->orderByRaw("TRY_CONVERT(datetime2, [Data emissao], 103) {$sortDir}");
                break;

            case 'tecnico':
                $query->orderBy('Tecnico', $sortDir);
                break;

            case 'dia_visita':
                $query->orderBy('data_visita_min', $sortDir);
                break;

            case 'operador':
                $query->orderBy('Operador', $sortDir);
                break;

            case 'qtd_os':
                $query->orderBy('itens_count', $sortDir);
                break;

            case 'numero_roteiro':
            default:
                $query->orderByRaw("TRY_CONVERT(INT, [Numero roteiro]) {$sortDir}");
                break;
        }

        if ($sortKey !== 'numero_roteiro') {
            $query->orderByRaw("TRY_CONVERT(INT, [Numero roteiro]) DESC");
        }
    }

    /**
     * Display the route planning page (creation)
     */
    public function create(Request $request)
    {
        // Fetch technicians (Funcionários)
        $tecnicos = DB::table('Funcionários')
            ->select('Codigo funcionario as codigo', 'Nome conhecido as nome')
            ->whereNull('Data demissão')
            ->orderBy('Nome conhecido')
            ->get();

        // Fetch cars (Carros)
        $carros = Carro::all();
        
        $preSelectedOs = $request->input('os', []);

        // Pre-select technician, car and date if passed from mapa-geral
        $tecnicoCodigo = $request->input('tecnico');
        $carroId = $request->input('carro');
        $dataVisita = $request->input('data_visita');
        $carroSelecionado = $carroId ? Carro::find($carroId) : null;

        return view('rotas.index', compact('tecnicos', 'carros', 'preSelectedOs', 'tecnicoCodigo', 'carroSelecionado', 'dataVisita'));
    }

    /**
     * Display the route planning page for editing
     */
    public function edit($numeroRoteiro)
    {
        $roteiro = RoteiroTecnico::with([
            'itens' => function ($query) {
                $query->orderBy('Seq tecnico', 'asc');
            },
            'itens.ordem.clienteByCodigo',
            'itens.ordem.ordem_api',
        ])->findOrFail($numeroRoteiro);

        $carroSelecionado = null;

        /**
         * =====================================================
         * 1️⃣ PRIORIDADE: carro_id vindo da ordem_api
         * Basta existir -> escolhe (mais frequente)
         * =====================================================
         */
        $carroIdMaisFrequente = collect($roteiro->itens)
            ->pluck('ordem.ordem_api.carro_id')
            ->filter()
            ->countBy()      // conta ocorrências
            ->sortDesc()     // mais frequente primeiro
            ->keys()
            ->first();

        if ($carroIdMaisFrequente) {
            $carroSelecionado = Carro::find($carroIdMaisFrequente);
        }

        /**
         * =====================================================
         * 2️⃣ FALLBACK: extrair apelido via campo Atendente
         * Achou um apelido? Já era.
         * =====================================================
         */
        if (! $carroSelecionado) {

            $apelidoMaisFrequente = collect($roteiro->itens)
                ->map(function ($item) {
                    return $this->extrairApelidosCarro(
                        $item->ordem->Atendente ?? null
                    );
                })
                ->flatten()     // junta todos os apelidos em um array só
                ->filter()
                ->countBy()
                ->sortDesc()
                ->keys()
                ->first();

            if ($apelidoMaisFrequente) {
                $carroSelecionado = Carro::whereRaw(
                    'apelido = CAST(? AS NVARCHAR(255))',
                    [(string) $apelidoMaisFrequente]
                )->first();
            }
        }

        /**
         * =====================================================
         * 3️⃣ AJUDANTES: identifica ajudantes ja definidos
         * =====================================================
         */
        $ajudantesDefinidos = [
            'ajudante1' => collect($roteiro->itens)->pluck('ordem.ordem_api.ajudante1')->filter()->first(),
            'ajudante2' => collect($roteiro->itens)->pluck('ordem.ordem_api.ajudante2')->filter()->first(),
            'ajudante3' => collect($roteiro->itens)->pluck('ordem.ordem_api.ajudante3')->filter()->first(),
            'ajudante4' => collect($roteiro->itens)->pluck('ordem.ordem_api.ajudante4')->filter()->first(),
        ];

        /**
         * =====================================================
         * 4️⃣ TECNICO: identifica o codigo do tecnico
         * =====================================================
         */
        $tecnicoNome = $roteiro->Tecnico;
        $tecnicoCodigo = DB::table('Funcionários')
            ->where('Nome conhecido', $tecnicoNome)
            ->value('Codigo funcionario');

        /**
         * =====================================================
         * Dados auxiliares da tela
         * =====================================================
         */
        $tecnicos = DB::table('Funcionários')
            ->select(
                'Codigo funcionario as codigo',
                'Nome conhecido as nome'
            )
            ->whereNull('Data demissão')
            ->orderBy('Nome conhecido')
            ->get();

        $carros = Carro::all();

        $preSelectedOs = request()->input('os', []);

        return view('rotas.index', compact(
            'roteiro',
            'tecnicos',
            'carros',
            'carroSelecionado',
            'ajudantesDefinidos',
            'tecnicoCodigo',
            'preSelectedOs'
        ));
    }

    /**
     * =====================================================
     * Helper: extrai apelido do carro
     * Regra: última palavra ANTES do hífen
     *
     * Exemplos:
     * ADRIAN S11 - 1º      => S11
     * BERNARDO TORO14 - 2º => TORO14
     * =====================================================
     */
    private function extrairApelidosCarro(?string $atendente): array
    {
        if (! $atendente || ! str_contains($atendente, '-')) {
            return [];
        }

        // pega tudo antes do hífen
        [$antesHifen] = explode('-', $atendente, 2);

        // quebra por espaço e pega a última palavra
        $partes = preg_split('/\s+/', trim($antesHifen));
        $apelido = end($partes);

        return $apelido ? [$apelido] : [];
    }

    /**
     * Fetch latest OS for the route screen
     */
    public function latestOs(Request $request)
    {
        $query = Ordem::query()
            ->select([
                'Numero ordem',
                'Codigo cliente',
                'Setor',
                'Situacao',
                'Data emissao',
            ])
            ->with([
                'clienteByCodigo' => function ($query) {
                    $query->select([
                        'Codigo cliente',
                        'Nome conhecido',
                        'Nome cliente',
                        'Endereco',
                        'Numero',
                        'Bairro',
                        'Cidade',
                        'Uf',
                        'Cep',
                    ]);
                },
                'ordem_api' => function ($query) {
                    $query->select([
                        'numero_ordem',
                        'tempo_estimado',
                        'alerta_horario',
                        'ajudante1',
                        'ajudante2',
                        'ajudante3',
                        'ajudante4',
                    ]);
                },
            ])
            ->where('Situacao', '!=', 'FINALIZADA')
            ->whereDoesntHave('roteiroItem');

        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->whereHas('clienteByCodigo', function ($q) use ($search) {
                $q->where('Nome conhecido', 'like', "%{$search}%")
                    ->orWhere('Nome cliente', 'like', "%{$search}%");
            });
        }

        if ($request->has('Setor') && ! empty($request->Setor)) {
            $query->where('Setor', $request->Setor);
        }

        if ($request->has('number') && ! empty($request->number)) {
            $query->where('Numero ordem', 'like', "%{$request->number}%");
        }

        // Filter by day of the week (Situacao field)
        if ($request->has('day') && ! empty($request->day)) {
            $dayMap = [
                'segunda' => '2º FEIRA',
                'terca' => '3º FEIRA',
                'quarta' => '4º FEIRA',
                'quinta' => '5º FEIRA',
                'sexta' => '6º FEIRA',
                'sabado' => 'SABADO',
                'domingo' => 'DOMINGO',
            ];

            $situacao = $dayMap[$request->day] ?? null;
            if ($situacao) {
                $query->where('Situacao', $situacao);
            }
        }

        $ordens = $query->orderBy('Data emissao', 'desc')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $ordens->items(),
            'current_page' => $ordens->currentPage(),
            'last_page' => $ordens->lastPage(),
        ]);
    }

    /**
     * Reschedule an OS to a different day
     */
    public function rescheduleOs(Request $request)
    {
        $traceId = (string) \Illuminate\Support\Str::uuid();

        Log::info('[ROTAS][RESCHEDULE][START]', [
            'trace_id' => $traceId,
            'payload' => $request->only(['os_number', 'new_situacao', 'new_date']),
            'user' => auth()->check() ? (auth()->user()->email ?? auth()->user()->name) : 'guest',
        ]);

        try {
            $validated = $request->validate([
                'os_number' => 'required',
                'new_situacao' => 'nullable|string',
                'new_date' => 'nullable|date',
                'new_tecnico' => 'nullable|string',
                'car_id' => 'required',
            ]);

            $osNumber = $validated['os_number'];
            $newSituacao = $validated['new_situacao'] ?? null;
            $newDate = $validated['new_date'] ?? null;
            $newTecnico = $validated['new_tecnico'] ?? null;
            $carId = $validated['car_id'];

            Log::info('[ROTAS][RESCHEDULE][VALIDATED]', [
                'trace_id' => $traceId,
                'os_number' => $osNumber,
                'new_situacao_in' => $newSituacao,
                'new_date_in' => $newDate,
                'car_id' => $carId,
            ]);

            $carro = Carro::find($carId);
            if (! $carro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Carro não encontrado.',
                ], 404);
            }

            $apiDay = null;
            $dayName = null;

            if (! $newSituacao && $newDate) {
                // Map day name to Situacao format (e.g., '2º FEIRA')
                $diasSemanaMap = [
                    'Monday' => '2º FEIRA',
                    'Tuesday' => '3º FEIRA',
                    'Wednesday' => '4º FEIRA',
                    'Thursday' => '5º FEIRA',
                    'Friday' => '6º FEIRA',
                    'Saturday' => 'SABADO',
                    'Sunday' => 'DOMINGO',
                ];

                // Map to OrdemApi dia_visita values (e.g., 'segunda')
                // IMPORTANT: Sunday is mapped to 'segunda' to avoid DB CHECK constraint conflict
                $apiDayMap = [
                    'Monday' => 'segunda',
                    'Tuesday' => 'terca',
                    'Wednesday' => 'quarta',
                    'Thursday' => 'quinta',
                    'Friday' => 'sexta',
                    'Saturday' => 'sabado',
                    'Sunday' => 'domingo',
                ];

                $dateObj = Carbon::parse($newDate);
                $dayName = $dateObj->format('l');

                $newSituacao = $diasSemanaMap[$dayName] ?? 'ABERTA';

                $apiDay = $apiDayMap[$dayName] ?? null;

                Log::info('[ROTAS][RESCHEDULE][DAY_MAP]', [
                    'trace_id' => $traceId,
                    'parsed_date' => $dateObj->toDateString(),
                    'day_name' => $dayName,
                    'situacao_mapped' => $newSituacao,
                    'api_day_mapped' => $apiDay,
                ]);

                // Preliminary Update OrdemApi with both formatted day name and raw date
                $rows1 = OrdemApi::where('numero_ordem', $osNumber)->update([
                    'dia_visita' => $apiDay,
                    'data_visita' => $newDate,
                ]);

                Log::info('[ROTAS][RESCHEDULE][ORDEMAPIA_PREUPDATE]', [
                    'trace_id' => $traceId,
                    'rows_affected' => $rows1,
                    'set_dia_visita' => $apiDay,
                    'set_data_visita' => $newDate,
                ]);

                $ordemApiAfter1 = OrdemApi::where('numero_ordem', $osNumber)
                    ->first(['numero_ordem', 'dia_visita', 'data_visita', 'status', 'atendente', 'carro_id']);

                Log::info('[ROTAS][RESCHEDULE][ORDEMAPIA_AFTER_PREUPDATE]', [
                    'trace_id' => $traceId,
                    'ordem_api' => $ordemApiAfter1 ? $ordemApiAfter1->toArray() : null,
                ]);
            } else {
                Log::info('[ROTAS][RESCHEDULE][SKIP_DAY_MAP]', [
                    'trace_id' => $traceId,
                    'reason' => ! $newDate ? 'new_date_empty' : 'new_situacao_provided',
                    'new_situacao_in' => $newSituacao,
                    'new_date_in' => $newDate,
                ]);
            }

            DB::beginTransaction();
            Log::info('[ROTAS][RESCHEDULE][TX_BEGIN]', ['trace_id' => $traceId]);

            // Update Ordem Situacao
            $ordem = Ordem::where('Numero ordem', $osNumber)->first();

            Log::info('[ROTAS][RESCHEDULE][ORDEM_FETCH]', [
                'trace_id' => $traceId,
                'found' => (bool) $ordem,
                'os_number' => $osNumber,
            ]);

            // Se ordem não existir, melhor logar e abortar pra não virar erro bobo depois
            if (! $ordem) {
                DB::rollBack();
                Log::warning('[ROTAS][RESCHEDULE][ORDEM_NOT_FOUND]', [
                    'trace_id' => $traceId,
                    'os_number' => $osNumber,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'OS não encontrada.',
                ], 404);
            }

            // Store old route number to check for empty route later
            $oldRouteItem = RoteiroTecnicoItem::where('No os', $osNumber)->first();
            $oldRouteNumber = $oldRouteItem ? $oldRouteItem->{'Numero roteiro'} : null;

            Log::info('[ROTAS][RESCHEDULE][OLD_ROUTE]', [
                'trace_id' => $traceId,
                'old_route_number' => $oldRouteNumber,
                'had_old_item' => (bool) $oldRouteItem,
                'old_periodo' => $oldRouteItem->Periodo ?? null,
                'old_seq' => $oldRouteItem->{'Seq tecnico'} ?? null,
            ]);

            if ($newSituacao) {
                $updateData = ['Situacao' => $newSituacao];
            }

            if ($newTecnico) {
                $updateData['Tecnico'] = $newTecnico;
            }

            $ordemRows = $ordem->update($updateData);

            if ($newTecnico) {
                $ordem->refresh(); // Ensure we have the new technician name
            }

            Log::info('[ROTAS][RESCHEDULE][ORDEM_UPDATED]', [
                'trace_id' => $traceId,
                'rows_affected' => $ordemRows,
                'new_situacao_final' => $newSituacao,
            ]);

            Log::info('[ROTAS][RESCHEDULE][ORDEMAPIA_DATA]', [
                'trace_id' => $traceId,
                'set_status' => 'PENDING',
                'set_data_visita' => $newDate,
                'set_dia_visita' => $apiDay,
                'day_name' => $dayName,
            ]);

            // Find or Create Route for the new date and same technician
            if ($ordem->Tecnico) {
                Log::info('[ROTAS][RESCHEDULE][TECH_ASSIGNED]', [
                    'trace_id' => $traceId,
                    'tecnico' => $ordem->Tecnico,
                ]);

                $newRoute = RoteiroTecnico::where('Tecnico', $ordem->Tecnico)
                    ->whereDate('Data emissao', $newDate)
                    ->first();

                Log::info('[ROTAS][RESCHEDULE][FIND_ROUTE]', [
                    'trace_id' => $traceId,
                    'found' => (bool) $newRoute,
                    'search_date' => $newDate,
                ]);

                if (! $newRoute) {
                    $newRouteNumber = $this->gerarNumeroRoteiro();
                    $nomeOperador = Funcionarios::where('Email', auth()->user()->email)->value('Nome conhecido');

                    Log::info('[ROTAS][RESCHEDULE][CREATE_ROUTE]', [
                        'trace_id' => $traceId,
                        'new_route_number' => $newRouteNumber,
                        'operador' => $nomeOperador ?? 'SISTEMA',
                        'data_emissao' => $newDate,
                        'tecnico' => $ordem->Tecnico,
                    ]);

                    $newRoute = RoteiroTecnico::create([
                        'Numero roteiro' => $newRouteNumber,
                        'Data emissao' => $newDate,
                        'Tecnico' => $ordem->Tecnico,
                        'Operador' => $nomeOperador ?? 'SISTEMA',
                    ]);
                }

                // Calculate next sequence for this route
                $nextSeq = RoteiroTecnicoItem::where('Numero roteiro', $newRoute->{'Numero roteiro'})
                    ->where('Data visita', $newDate)
                    ->max('Seq tecnico') ?? 0;
                $nextSeq++;

                // Delete old item and create new one in the new route
                RoteiroTecnicoItem::where('No os', $osNumber)->delete();

                $createdItem = RoteiroTecnicoItem::create([
                    'Numero roteiro' => $newRoute->{'Numero roteiro'},
                    'No os' => $osNumber,
                    'Data visita' => $newDate,
                    'Periodo' => $oldRouteItem->Periodo ?? 'COMERCIAL',
                    'Seq tecnico' => $nextSeq,
                ]);

                // Update Atendente string (Tech Car - Seq) to sync with app
                $ordemApi = OrdemApi::where('numero_ordem', $osNumber)->first();
                $apelidoCarro = strtoupper($carro->apelido ?? $carro->placa ?? '');

                $sequenciaMapOrdinal = [
                    1 => '1º',
                    2 => '2º',
                    3 => '3º',
                    4 => '4º',
                    5 => '5º',
                    6 => '6º',
                    7 => '7º',
                    8 => '8º',
                    9 => '9º',
                    10 => '10º',
                    11 => '11º',
                    12 => '12º',
                    13 => '13º',
                    14 => '14º',
                    15 => '15º',
                ];
                $seqOrdinal = $sequenciaMapOrdinal[$nextSeq] ?? "{$nextSeq}º";
                $nomeTecnicoUpper = strtoupper($ordem->Tecnico);

                $ajudantes = [
                    $ordemApi->ajudante1 ?? null,
                    $ordemApi->ajudante2 ?? null,
                    $ordemApi->ajudante3 ?? null,
                    $ordemApi->ajudante4 ?? null,
                ];

                $atendenteString = OrdemApi::buildAtendenteString(
                    $nomeTecnicoUpper,
                    $apelidoCarro,
                    $seqOrdinal,
                    $ajudantes
                );

                $ordem->update(['Atendente' => $atendenteString]);

                // Update OrdemApi status to PENDING and update assignment
                $ordemApi->update([
                    'status' => 'PENDING',
                    'carro_id' => $carId,
                    'data_visita' => $newDate,
                    'dia_visita' => $apiDay,
                    'sequencia' => array_search($nextSeq, [
                        'primeira' => 1,
                        'segunda' => 2,
                        'terceira' => 3,
                        'quarta' => 4,
                        'quinta' => 5,
                        'sexta' => 6,
                        'setima' => 7,
                        'oitava' => 8,
                        'nona' => 9,
                        'decima' => 10,
                        'decima_primeira' => 11,
                        'decima_segunda' => 12,
                        'decima_terceira' => 13,
                        'decima_quarta' => 14,
                        'decima_quinta' => 15,
                        'decima_sexta' => 16,
                        'decima_setima' => 17,
                        'decima_oitava' => 18,
                        'decima_nona' => 19,
                        'vigesima' => 20,
                    ]) ?: 'primeira',
                ]);
            } else {
                Log::warning('[ROTAS][RESCHEDULE][NO_TECH]', [
                    'trace_id' => $traceId,
                    'os_number' => $osNumber,
                ]);

                $delCount = RoteiroTecnicoItem::where('No os', $osNumber)->delete();

                Log::info('[ROTAS][RESCHEDULE][DELETE_ITEM_NO_TECH]', [
                    'trace_id' => $traceId,
                    'deleted_count' => $delCount,
                ]);
            }

            // If the old route became empty, delete it
            if ($oldRouteNumber) {
                $remainingItems = RoteiroTecnicoItem::where('Numero roteiro', $oldRouteNumber)->count();

                Log::info('[ROTAS][RESCHEDULE][OLD_ROUTE_REMAINING]', [
                    'trace_id' => $traceId,
                    'old_route_number' => $oldRouteNumber,
                    'remaining_items' => $remainingItems,
                ]);

                if ($remainingItems === 0) {
                    $delRoute = RoteiroTecnico::where('Numero roteiro', $oldRouteNumber)->delete();

                    Log::info('[ROTAS][RESCHEDULE][OLD_ROUTE_DELETED]', [
                        'trace_id' => $traceId,
                        'old_route_number' => $oldRouteNumber,
                        'deleted_rows' => $delRoute,
                    ]);
                }
            }

            DB::commit();
            Log::info('[ROTAS][RESCHEDULE][TX_COMMIT]', ['trace_id' => $traceId]);

            Log::info('[ROTAS] OS reagendada e movida para roteiro', [
                'trace_id' => $traceId,
                'os_number' => $osNumber,
                'new_date' => $newDate,
                'day_name' => $dayName,
                'api_day' => $apiDay,
                'new_situacao_final' => $newSituacao,
                'new_route' => $newRoute->{'Numero roteiro'} ?? 'none',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'OS reagendada e movida com sucesso!',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[ROTAS][RESCHEDULE][VALIDATION_ERROR]', [
                'trace_id' => $traceId,
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro de validação: '.implode(', ', collect($e->errors())->flatten()->toArray()),
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('[ROTAS][RESCHEDULE][EXCEPTION]', [
                'trace_id' => $traceId,
                'message' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao reagendar OS: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove an OS from a route (un-assign)
     */
    /**
     * Remove an OS from a route (un-assign)
     */
    public function encerrarOs(Request $request)
    {
        try {
            $validated = $request->validate([
                'os_number' => 'required',
            ]);

            $osNumber = $validated['os_number'];

            DB::beginTransaction();

            // Store old route number to check for empty route later
            $oldRouteItem = RoteiroTecnicoItem::where('No os', $osNumber)->first();
            $oldRouteNumber = $oldRouteItem ? $oldRouteItem->{'Numero roteiro'} : null;

            // Remove from roteiro
            RoteiroTecnicoItem::where('No os', $osNumber)->delete();

            // If the old route became empty, delete it
            if ($oldRouteNumber) {
                $remainingItems = RoteiroTecnicoItem::where('Numero roteiro', $oldRouteNumber)->count();
                if ($remainingItems === 0) {
                    RoteiroTecnico::where('Numero roteiro', $oldRouteNumber)->delete();
                }
            }

            // Busca a ordem no PGI
            $ordem = Ordem::where('Numero ordem', $osNumber)->first();

            if ($ordem) {
                // Busca o funcionário responsável pelo email do usuário autenticado
                $funcionario = Funcionarios::where('Email', auth()->user()->email)->first();
                $responsavel = $funcionario ? $funcionario->{'Nome conhecido'} : auth()->user()->name;

                $now = Carbon::now();

                // 1. Atualizar campos Terminada, Encerrada, Tecnico, Atendente e Situacao na tabela Ordens
                // Usamos Eloquent para a Situação e DB raw para os campos bit
                $ordem->update([
                    'Situacao' => 'ABERTA',
                    'Tecnico' => '',
                    'Atendente' => '',
                ]);

                DB::table('Ordens')
                    ->where('Numero ordem', $osNumber)
                    ->update([
                        'Terminada' => 0,
                        'Encerrada' => 0,
                    ]);

                // 2. Limpar informações da visita em ordens_api
                $ordemApi = OrdemApi::where('numero_ordem', $osNumber)->first();
                if ($ordemApi) {
                    $ordemApi->update([
                        'atendente' => null,
                        'carro_id' => null,
                        'data_visita' => null,
                        'sequencia' => null,
                        'dia_visita' => null,
                    ]);
                }

                Log::info("✅ Ordem {$osNumber} removida do roteiro e retornada para ABERTA (via Rotas)");

                // 3. Inserir nova etapa na tabela Ordens situacao
                $novaSeqSitu = (DB::table('Ordens situacao')
                    ->where('Numero ordem', $osNumber)
                    ->max('Sequencia') ?? 0) + 1;

                DB::table('Ordens situacao')->insert([
                    'Numero ordem' => $osNumber,
                    'Sequencia' => $novaSeqSitu,
                    'Responsável' => $responsavel,
                    'Data inicio' => $now->toDateString(),
                    'Hora inicio' => $now->format('H:i:s'),
                    'Situacao' => 'ABERTA',
                    'Motivo situacao' => '',
                    'Data fim' => $now->toDateString(),
                    'Hora fim' => $now->format('H:i:s'),
                    'No tarefa' => 1,
                    'Automatico' => 1,
                    'No item' => 0,
                ]);

                // 4. Atualizar o sequencial
                DB::table('SYS~Sequencial')->updateOrInsert(
                    [
                        'SYS~BD' => 'DADOSPGI',
                        'SYS~Tabela' => 'Ordens situacao',
                        'SYS~Campo' => 'Sequencia',
                        'SYS~Chave' => (string) $osNumber,
                    ],
                    [
                        'PW~Projeto' => '',
                        'SYS~Valor' => $novaSeqSitu,
                        'SYS~Estacao' => 'AGENT',
                        'SYS~Identificacao' => 'ROTAS_PRONTAS',
                        'SYS~Pendentes' => 1,
                    ]
                );

                Log::info("✅ Etapa ABERTA criada para ordem {$osNumber} (seq {$novaSeqSitu}) - Responsável: {$responsavel}");
            }

            DB::commit();

            Log::info("[ROTAS] OS $osNumber removida do roteiro");

            return response()->json([
                'success' => true,
                'message' => 'OS removida do roteiro com sucesso!',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ROTAS] Erro ao remover OS do roteiro: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao remover OS: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Geocode an address to coordinates using Nominatim API
     */
    public function geocode(Request $request)
    {
        $request->validate([
            'address' => 'required|string|max:500',
        ]);

        $address = $request->input('address');
        $cacheKey = 'rotas:geocode:address:'.md5(mb_strtolower(trim($address)));

        try {
            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult) {
                return response()->json([
                    'success' => true,
                    'data' => $cachedResult,
                ]);
            }

            // Try multiple address formats for better success rate
            $searchQueries = [
                $address,
                preg_replace('/, Brasil$/', '', $address), // Remove country if present
                explode(',', $address)[0].', '.(explode(',', $address)[2] ?? ''), // Street + City
            ];

            foreach ($searchQueries as $query) {
                $response = Http::withHeaders([
                    'User-Agent' => 'TecnoarTech/1.0',
                ])->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'br',
                    'addressdetails' => 1,
                ]);

                if ($response->successful() && count($response->json()) > 0) {
                    $result = $response->json()[0];
                    $payload = [
                        'lat' => (float) $result['lat'],
                        'lon' => (float) $result['lon'],
                        'display_name' => $result['display_name'],
                    ];

                    Cache::put($cacheKey, $payload, now()->addDays(30));

                    return response()->json([
                        'success' => true,
                        'data' => $payload,
                    ]);
                }
            }

            Log::warning('[ROTAS] Geocodificação falhou para o endereço: '.$address);

            return response()->json([
                'success' => false,
                'message' => 'Endereço não encontrado. Tente ser mais específico (ex: Rua, Número, Cidade).',
            ], 404);

        } catch (\Exception $e) {
            Log::error('[ROTAS] Erro ao geocodificar endereço: '.$e->getMessage(), [
                'address' => $address,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar endereço: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Geocode an address using CEP and number
     */
    public function geocodeByCep(Request $request)
    {
        $request->validate([
            'cep' => 'required|string|max:10',
            'numero' => 'required|string|max:10',
        ]);

        $cep = preg_replace('/\D/', '', $request->input('cep'));
        $numero = $request->input('numero');
        $cacheKey = 'rotas:geocode:cep:'.md5($cep.'|'.mb_strtolower(trim((string) $numero)));

        try {
            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult) {
                return response()->json([
                    'success' => true,
                    'data' => $cachedResult,
                ]);
            }

            // First, get address from ViaCEP
            $viaCepResponse = Http::get("https://viacep.com.br/ws/{$cep}/json/");

            if (! $viaCepResponse->successful() || isset($viaCepResponse->json()['erro'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'CEP não encontrado.',
                ], 404);
            }

            $addressData = $viaCepResponse->json();

            // Build full address for geocoding
            $fullAddress = sprintf(
                '%s, %s, %s - %s, Brasil',
                $addressData['logradouro'],
                $numero,
                $addressData['bairro'],
                $addressData['localidade']
            );

            // Try multiple address formats for better success rate
            $searchQueries = [
                sprintf('%s, %s, %s - %s, Brasil', $addressData['logradouro'], $numero, $addressData['bairro'], $addressData['localidade']),
                sprintf('%s, %s, %s, Brasil', $addressData['logradouro'], $numero, $addressData['localidade']),
                sprintf('%s, %s, Brasil', $addressData['logradouro'], $addressData['localidade']),
                sprintf('%s, %s, Brasil', $addressData['cep'], $numero),
            ];

            foreach ($searchQueries as $query) {
                $response = Http::withHeaders([
                    'User-Agent' => 'TecnoarTech/1.0',
                ])->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'br',
                    'addressdetails' => 1,
                ]);

                if ($response->successful() && count($response->json()) > 0) {
                    $result = $response->json()[0];
                    $payload = [
                        'lat' => (float) $result['lat'],
                        'lon' => (float) $result['lon'],
                        'display_name' => $query, // Use the query that worked
                    ];

                    Cache::put($cacheKey, $payload, now()->addDays(30));

                    return response()->json([
                        'success' => true,
                        'data' => $payload,
                    ]);
                }
            }

            Log::warning('[ROTAS] Geocodificação por CEP falhou: '.$cep.' nº '.$numero);

            return response()->json([
                'success' => false,
                'message' => 'Não foi possível geolocalizar o ponto pelo CEP e número. Tente adicionar pelo endereço completo.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('[ROTAS] Erro ao geocodificar por CEP: '.$e->getMessage(), [
                'cep' => $cep,
                'numero' => $numero,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar endereço: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate route between multiple points using OSRM API
     */
    public function calculateRoute(Request $request)
    {
        $request->validate([
            'points' => 'required|array|min:2',
            'points.*.lat' => 'required|numeric',
            'points.*.lon' => 'required|numeric',
        ]);

        $points = $request->input('points');

        try {
            // Build coordinates string for OSRM
            $coordinates = collect($points)
                ->map(fn ($point) => $point['lon'].','.$point['lat'])
                ->join(';');

            // Use OSRM routing service
            $response = Http::get("https://router.project-osrm.org/route/v1/driving/{$coordinates}", [
                'overview' => 'full',
                'geometries' => 'geojson',
                'steps' => 'true',
            ]);

            if ($response->successful()) {
                $result = $response->json();

                if (isset($result['routes'][0])) {
                    $route = $result['routes'][0];

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'distance' => $route['distance'], // in meters
                            'duration' => $route['duration'], // in seconds
                            'geometry' => $route['geometry']['coordinates'],
                        ],
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Não foi possível calcular a rota.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('[ROTAS] Erro ao calcular rota: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao calcular rota. Tente novamente.',
            ], 500);
        }
    }

    /**
     * Save the planned route
     */
    public function saveRoute(Request $request)
    {
        $request->validate([
            'technician_id' => 'required',
            'car_id' => 'required',
            'day' => 'required',
            'os_list' => 'required|array|max:20',
            'numero_roteiro' => 'nullable',
            'ajudante1' => 'nullable|string',
            'ajudante2' => 'nullable|string',
            'ajudante3' => 'nullable|string',
            'ajudante4' => 'nullable|string',
        ]);

        $technicianId = $request->input('technician_id');
        $carId = $request->input('car_id');
        $day = $request->input('day');
        $osList = $request->input('os_list');
        $numeroRoteiro = $request->input('numero_roteiro');

        $ajudantes_input = [
            $request->input('ajudante1'),
            $request->input('ajudante2'),
            $request->input('ajudante3'),
            $request->input('ajudante4'),
        ];

        // Fetch technician name
        $technician = DB::table('Funcionários')
            ->where('Codigo funcionario', $technicianId)
            ->first();

        if (! $technician) {
            return response()->json([
                'success' => false,
                'message' => 'Técnico não encontrado.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            if (! $numeroRoteiro) {
                $numeroRoteiro = $this->gerarNumeroRoteiro();
                // If it's a new route, set emission date to today
                $dataToUpdate['Data emissao'] = now()->format('Y-m-d');
            }

            $currentDataEmissao = RoteiroTecnico::where('Numero roteiro', $numeroRoteiro)->value('Data emissao');
            if (! $currentDataEmissao && ! isset($dataToUpdate['Data emissao'])) {
                // Fallback: if somehow we are updating but it has no date, set it.
                $dataToUpdate['Data emissao'] = now()->format('Y-m-d');
            }

            $nomeOperador = Funcionarios::where('Email', auth()->user()->email)->value('Nome conhecido');

            // Save/Update RoteiroTecnico
            $roteiro = RoteiroTecnico::updateOrCreate(
                ['Numero roteiro' => $numeroRoteiro],
                array_merge([
                    'Tecnico' => $technician->{'Nome conhecido'},
                    'Operador' => $nomeOperador ?? 'API',
                ], $dataToUpdate ?? [])
            );

            // =====================================================
            // REORGANIZAÇÃO DE ITENS DO ROTEIRO
            // =====================================================
            // Este processo SEMPRE deleta todos os itens existentes e recria
            // com a sequência correta, garantindo que não haja inconsistências

            // Validar que não há OSs duplicadas na lista
            $osNumbers = array_column($osList, 'number');
            if (count($osNumbers) !== count(array_unique($osNumbers))) {
                throw new \Exception('A lista de OS contém duplicatas. Cada OS deve aparecer apenas uma vez.');
            }

            // Contar itens existentes antes da deleção (para auditoria)
            $existingItemsCount = RoteiroTecnicoItem::where('Numero roteiro', $numeroRoteiro)->count();

            // Deletar TODOS os itens existentes do roteiro
            RoteiroTecnicoItem::where('Numero roteiro', $numeroRoteiro)->delete();

            Log::info('[ROTAS] Reorganização de itens do roteiro', [
                'numero_roteiro' => $numeroRoteiro,
                'itens_deletados' => $existingItemsCount,
                'novos_itens' => count($osList),
                'tecnico' => $technician->{'Nome conhecido'},
            ]);

            // Recriar itens com a sequência correta
            foreach ($osList as $index => $osData) {
                $osNumber = $osData['number'];

                // Map numeric sequence to string values used in the database
                $sequenciaMap = [
                    1 => 'primeira',
                    2 => 'segunda',
                    3 => 'terceira',
                    4 => 'quarta',
                    5 => 'quinta',
                    6 => 'sexta',
                    7 => 'setima',
                    8 => 'oitava',
                    9 => 'nona',
                    10 => 'decima',
                    11 => 'decima_primeira',
                    12 => 'decima_segunda',
                    13 => 'decima_terceira',
                    14 => 'decima_quarta',
                    15 => 'decima_quinta',
                    16 => 'decima_sexta',
                    17 => 'decima_setima',
                    18 => 'decima_oitava',
                    19 => 'decima_nona',
                    20 => 'vigesima',
                ];
                $sequence = $sequenciaMap[$index + 1] ?? ($index + 1).'a'; // Use 8a, 9a etc for overflow

                // Save RoteiroTecnicoItem com sequência reorganizada
                RoteiroTecnicoItem::create([
                    'Numero roteiro' => $numeroRoteiro,
                    'No os' => $osNumber,
                    'Data visita' => $day,
                    'Periodo' => $osData['periodo'] ?? 'COMERCIAL',
                    'Seq tecnico' => $index + 1,
                ]);

                // Map date to day name for ordens_api constraint and Situacao
                // IMPORTANT: Sunday is mapped to 'segunda' to avoid DB CHECK constraint conflict
                $diasSemanaMap = [
                    'Monday' => 'segunda',
                    'Tuesday' => 'terca',
                    'Wednesday' => 'quarta',
                    'Thursday' => 'quinta',
                    'Friday' => 'sexta',
                    'Saturday' => 'sabado',
                    'Sunday' => 'domingo',
                ];
                $dateObj = \Carbon\Carbon::parse($day);
                $dayName = $diasSemanaMap[$dateObj->format('l')] ?? 'segunda';

                // Map day name to Situacao format (e.g., '2º FEIRA')
                $situacaoMap = [
                    'segunda' => '2º FEIRA',
                    'terca' => '3º FEIRA',
                    'quarta' => '4º FEIRA',
                    'quinta' => '5º FEIRA',
                    'sexta' => '6º FEIRA',
                    'sabado' => 'SABADO',
                    'domingo' => 'DOMINGO',
                ];
                $situacao = $situacaoMap[$dayName] ?? '';

                // Map sequence to ordinal string (e.g., '1º')
                $sequenciaMap = [
                    'primeira' => '1º',
                    'segunda' => '2º',
                    'terceira' => '3º',
                    'quarta' => '4º',
                    'quinta' => '5º',
                    'sexta' => '6º',
                    'setima' => '7º',
                    'oitava' => '8º',
                    'nona' => '9º',
                    'decima' => '10º',
                    'decima_primeira' => '11º',
                    'decima_segunda' => '12º',
                    'decima_terceira' => '13º',
                    'decima_quarta' => '14º',
                    'decima_quinta' => '15º',
                    'decima_sexta' => '16º',
                    'decima_setima' => '17º',
                    'decima_oitava' => '18º',
                    'decima_nona' => '19º',
                    'vigesima' => '20º',
                ];
                $sequenciaOrdinal = $sequenciaMap[$sequence] ?? ($index + 1).'º';

                // Get Car nickname
                $carro = Carro::find($carId);
                $apelidoCarro = $carro ? strtoupper($carro->apelido ?? $carro->placa) : '';
                $nomeTecnico = strtoupper($technician->{'Nome conhecido'});

                // Construct Atendente string using helpers
                $atendente = OrdemApi::buildAtendenteString(
                    $nomeTecnico,
                    $apelidoCarro,
                    $sequenciaOrdinal,
                    $ajudantes_input
                );

                // Update main Ordem table
                Ordem::where('Numero ordem', $osNumber)->update([
                    'Tecnico' => $technician->{'Nome conhecido'},
                    'Atendente' => $atendente,
                    'Situacao' => $situacao,
                ]);

                // Update/Create OrdemApi entry
                OrdemApi::updateOrCreate(
                    ['numero_ordem' => $osNumber],
                    [
                        'atendente' => $technician->{'Nome conhecido'},
                        'carro_id' => $carId,
                        'dia_visita' => $dayName,
                        'sequencia' => $sequence,
                        'tempo_estimado' => $osData['tempo_estimado'] ?? null,
                        'alerta_horario' => $osData['alerta_horario'] ?? null,
                        'ajudante1' => $ajudantes_input[0],
                        'ajudante2' => $ajudantes_input[1],
                        'ajudante3' => $ajudantes_input[2],
                        'ajudante4' => $ajudantes_input[3],
                    ]
                );
            }

            Log::info('[ROTAS] Itens do roteiro criados com sucesso', [
                'numero_roteiro' => $numeroRoteiro,
                'total_itens' => count($osList),
                'sequencias' => array_map(fn ($i) => $i + 1, array_keys($osList)),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Roteiro gerado e vinculado com sucesso!',
                'numero_roteiro' => $numeroRoteiro,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ROTAS] Erro ao salvar roteiro: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar roteiro: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate a new sequential number for RoteiroTecnico
     */
    public function gerarNumeroRoteiro(): int
    {
        $tentativas = 0;
        $maxTentativas = 3;

        do {
            try {
                return DB::transaction(function () use (&$tentativas) {
                    DB::statement('SET LOCK_TIMEOUT 2000'); // 2s

                    $sequencia = DB::table('SYS~Sequencial')
                        ->where('SYS~Tabela', 'Roteiros tecnicos')
                        ->where('SYS~Campo', 'Numero roteiro')
                        ->lockForUpdate()
                        ->first();

                    if (! $sequencia) {
                        // If not found, create it starting from the current max
                        $max = (int) DB::table('Roteiros tecnicos')->max('Numero roteiro');
                        $valorAtual = $max;
                        $novoValor = $max + 1;

                        DB::table('SYS~Sequencial')->insert([
                            'SYS~Tabela' => 'Roteiros tecnicos',
                            'SYS~Campo' => 'Numero roteiro',
                            'SYS~Valor' => $novoValor,
                            'SYS~ValorAnterior' => $valorAtual,
                            'PW~Projeto' => 'TECNOAR',
                            'SYS~BD' => 'TECNOAR',
                        ]);
                    } else {
                        $valorAtual = (int) $sequencia->{'SYS~Valor'};
                        $novoValor = $valorAtual + 1;

                        // Verify if it already exists
                        while (DB::table('Roteiros tecnicos')->where('Numero roteiro', $novoValor)->exists()) {
                            $novoValor++;
                        }

                        DB::table('SYS~Sequencial')
                            ->where('SYS~Tabela', 'Roteiros tecnicos')
                            ->where('SYS~Campo', 'Numero roteiro')
                            ->where('SYS~Valor', $valorAtual)
                            ->update([
                                'SYS~ValorAnterior' => $valorAtual,
                                'SYS~Valor' => $novoValor,
                            ]);
                    }

                    return $novoValor;
                });
            } catch (\Exception $e) {
                $tentativas++;
                if ($tentativas >= $maxTentativas) {
                    throw $e;
                }
                usleep(100000 * $tentativas);
            }
        } while (true);
    }

    public function updateOsDetails(Request $request)
    {
        try {
            $validated = $request->validate([
                'os_number' => 'required',
                'tempo_estimado' => 'nullable|integer',
                'alerta_horario' => 'nullable|date_format:H:i',
            ]);

            OrdemApi::updateOrCreate(
                ['numero_ordem' => $validated['os_number']],
                [
                    'tempo_estimado' => $validated['tempo_estimado'],
                    'alerta_horario' => $validated['alerta_horario'],
                ]
            );

            return response()->json(['success' => true, 'message' => 'Detalhes atualizados com sucesso!']);
        } catch (\Exception $e) {
            Log::error('[ROTAS] Erro ao atualizar detalhes da OS: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Erro ao atualizar detalhes.'], 500);
        }
    }

    /**
     * Generate PDF for a technical route
     */
    public function gerarPdfRoteiro($numeroRoteiro)
    {
        // Load route with items and related orders
        $roteiro = RoteiroTecnico::with([
            'itens.ordem.clienteByCodigo',
        ])->findOrFail($numeroRoteiro);

        // Get items ordered by sequence
        $itens = $roteiro->itens()
            ->with('ordem')
            ->orderBy('Seq tecnico', 'asc')
            ->get();

        // Extract general information
        $dataVisita = null;
        $carro = null;

        if ($itens->isNotEmpty()) {
            // Get visit date from first item
            $firstItem = $itens->first();
            if ($firstItem->{'Data visita'}) {
                $dataVisita = \Carbon\Carbon::parse($firstItem->{'Data visita'})->format('d/m/Y');
            }

            // Try to get car from ordem_api
            $carroId = $firstItem->ordem->ordem_api->carro_id ?? null;
            if ($carroId) {
                $carroModel = Carro::find($carroId);
                if ($carroModel) {
                    $carro = $carroModel->apelido ?? $carroModel->placa;
                }
            }

            // Fallback: extract car from Atendente field
            if (! $carro) {
                $atendente = $firstItem->ordem->Atendente ?? null;
                if ($atendente) {
                    // Extract car nickname from format: "TECNICO CARRO - SEQ"
                    $parts = explode(' - ', $atendente);
                    if (count($parts) > 0) {
                        $beforeSeq = trim($parts[0]);
                        $words = explode(' ', $beforeSeq);
                        // Last word before sequence is usually the car
                        if (count($words) > 1) {
                            $carro = end($words);
                        }
                    }
                }
            }
        }

        // Generate PDF
        $pdf = Pdf::loadView('rotas.pdf-roteiro', compact('roteiro', 'itens', 'dataVisita', 'carro'))
            ->setOptions([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ]);

        return $pdf->stream("roteiro-tecnico-{$numeroRoteiro}.pdf");
    }

    /**
     * Display the global map view of all pending OS
     */
    public function mapaGeral()
    {
        $tecnicos = DB::table('Funcionários')
            ->select('Codigo funcionario as codigo', 'Nome conhecido as nome')
            ->whereNull('Data demissão')
            ->orderBy('Nome conhecido')
            ->get();

        // Calcular próximo dia útil
        $nextDay = now()->addDay();
        while ($nextDay->isWeekend()) {
            $nextDay->addDay();
        }
        $defaultDate = $nextDay->toDateString();

        // Fetch cars (Carros)
        $carros = Carro::all();

        return view('rotas.mapa-geral', compact('tecnicos', 'carros', 'defaultDate'));
    }

    /**
     * Fetch OS data for global map
     */
    public function mapaGeralData(Request $request)
    {
        $query = Ordem::query()
            ->select([
                'Numero ordem',
                'Codigo cliente',
                'Setor',
                'Situacao',
                'Data emissao',
                'Tecnico',
            ])
            ->with([
                'clienteByCodigo' => function ($query) {
                    $query->select([
                        'Codigo cliente',
                        'Nome conhecido',
                        'Nome cliente',
                        'Endereco',
                        'Numero',
                        'Bairro',
                        'Cidade',
                        'Uf',
                        'Cep',
                    ]);
                },
                'ordem_api' => function ($query) {
                    $query->select([
                        'numero_ordem',
                        'tempo_estimado',
                        'alerta_horario',
                        'data_visita',
                        'checklist_json',
                    ]);
                },
                'roteiroItem.roteiro'
            ])
            ->where('Situacao', '!=', 'FINALIZADA');

        // Pega as datas do request
        $dateStart = $request->data_inicio;
        $dateEnd = $request->data_fim;

        // Busca estrita APENAS por Data de Visita (ordens_api)
        // Usamos whereRaw para garantir compatibilidade com SQL Server e evitar falhas de conversão implícita
        $sq = \App\Models\OrdemApi::query()
            ->whereNotNull('data_visita');
        if ($dateStart) {
            $sq->whereRaw("CONVERT(date, data_visita) >= ?", [$dateStart]);
        }
        if ($dateEnd) {
            $sq->whereRaw("CONVERT(date, data_visita) <= ?", [$dateEnd]);
        }
        
        $osIds = $sq->pluck('numero_ordem')->toArray();

        // Filtra as OSs que possuem esses registros de visita
        if (empty($osIds)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $query->whereIn('Numero ordem', $osIds);

        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->whereHas('clienteByCodigo', function ($q) use ($search) {
                $q->where('Nome conhecido', 'like', "%{$search}%")
                    ->orWhere('Nome cliente', 'like', "%{$search}%");
            });
        }

        if ($request->has('Setor') && ! empty($request->Setor) && $request->Setor !== 'Todos') {
            $query->where('Setor', $request->Setor);
        }

        if ($request->has('tecnico') && ! empty($request->tecnico)) {
            $query->where('Tecnico', $request->tecnico);
        }


        
        if ($request->has('os_in') && is_array($request->os_in) && count($request->os_in) > 0) {
            $query->whereIn('Numero ordem', $request->os_in);
            // Overwrite the date filter to ensure we get specific OS no matter the date
            $query->getQuery()->wheres = array_filter($query->getQuery()->wheres, function ($where) {
                return !($where['type'] == 'Date' && $where['column'] instanceof \Illuminate\Database\Query\Expression && str_contains($where['column']->getValue(), '[Data emissao]'));
            });
        }

        // Aumentando o limite para garantir que OSs mais antigas com visita agendada apareçam
        $ordens = $query->orderByRaw('TRY_CONVERT(datetime2, [Data emissao], 103) DESC')->limit(2000)->get();
        
        $mappedData = $ordens->map(function ($os) {
            $address = '';
            $cep = '';
            $numero = '1';
            
            if ($os->clienteByCodigo) {
                $addressParts = [];
                if ($os->clienteByCodigo->Endereco) $addressParts[] = trim($os->clienteByCodigo->Endereco);
                if ($os->clienteByCodigo->Numero) { 
                    $addressParts[] = trim($os->clienteByCodigo->Numero); 
                    $numero = trim($os->clienteByCodigo->Numero); 
                }
                if ($os->clienteByCodigo->Bairro) $addressParts[] = trim($os->clienteByCodigo->Bairro);
                if ($os->clienteByCodigo->Cidade) $addressParts[] = trim($os->clienteByCodigo->Cidade);
                if ($os->clienteByCodigo->Uf) $addressParts[] = trim($os->clienteByCodigo->Uf);
                $address = implode(', ', array_filter($addressParts));
                $cep = $os->clienteByCodigo->Cep ?? '';
            }

            $lat = null;
            $lon = null;
            
            // Try to find in cache exactly how RotasController@geocodeByCep / geocode does it:
            $cepClean = preg_replace('/\D/', '', $cep);
            if ($cepClean && $numero) {
                $cacheKeyCep = 'rotas:geocode:cep:'.md5($cepClean.'|'.mb_strtolower(trim((string)$numero)));
                $cached = Cache::get($cacheKeyCep);
                if ($cached) {
                    $lat = $cached['lat'];
                    $lon = $cached['lon'];
                }
            }
            if (!$lat && $address) {
                $addressStr = preg_match('/,\s*Brasil$/i', $address) ? $address : "{$address}, Brasil";
                $cacheKeyAddress = 'rotas:geocode:address:'.md5(mb_strtolower(trim($addressStr)));
                $cached = Cache::get($cacheKeyAddress);
                if ($cached) {
                    $lat = $cached['lat'];
                    $lon = $cached['lon'];
                }
            }

            $hasRoute = $os->roteiroItem ? true : false;
            $tecnicoNome = $hasRoute ? ($os->roteiroItem->roteiro->Tecnico ?? $os->Tecnico) : $os->Tecnico;

            return [
                'numero_ordem' => $os->{'Numero ordem'},
                'cliente' => $os->clienteByCodigo->{'Nome conhecido'} ?? $os->clienteByCodigo->{'Nome cliente'} ?? 'Sem Nome',
                'endereco' => $address,
                'cep' => $cep,
                'numero' => $numero,
                'tecnico' => $tecnicoNome,
                'has_route' => $hasRoute,
                'route_date' => $hasRoute && $os->roteiroItem->{'Data visita'} ? \Carbon\Carbon::parse($os->roteiroItem->{'Data visita'})->format('d/m/Y') : null,
                'setor' => $os->Setor,
                'situacao' => $os->Situacao,
                'data_emissao' => $os->{'Data emissao'},
                'data_visita' => $os->ordem_api->data_visita ? $os->ordem_api->data_visita->format('d/m/Y') : 'N/A',
                'tempo_estimado' => $os->ordem_api->tempo_estimado ?? 60,
                'checklist' => $os->ordem_api->checklist_json,
                'lat' => $lat,
                'lon' => $lon,
                'numero_roteiro' => $hasRoute ? ($os->roteiroItem->{'Numero roteiro'} ?? null) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $mappedData,
        ]);
    }
}
