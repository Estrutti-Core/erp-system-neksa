<?php

namespace App\Http\Controllers;

use App\Models\EntradaPecaPagto;
use App\Models\Filial;
use App\Models\Fornecedor;
use App\Models\OrdemPagto;
use App\Models\RetornoProcessamento;
use App\Models\RetornoProcessamentoItem;
use App\Models\ServicosImportacao;
use App\Models\ServicosImportacaoItem;
use App\Models\Vendas;
use App\Models\VendasPagto;
use App\Services\Financeiro\BankApiSettlementService;
use App\Services\Financeiro\Banking\SantanderBankSettlementProvider;
use App\Services\Financeiro\BoletoBaixaService;
use App\Services\Financeiro\Concerns\ParsesMarketplaceAmounts;
use App\Services\Financeiro\MagaluReturnService;
use App\Services\Financeiro\MercadoLivreReturnService;
use App\Services\Financeiro\RetornoCnabService;
use App\Services\Financeiro\RetornoProcessamentoLoggerService;
use App\Services\Financeiro\ShopeeReturnService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

class FinanceiroController extends Controller
{
    use ParsesMarketplaceAmounts;

    private const BANCOS_VALIDOS = ['033', '237', '023'];

    private const SQLSERVER_SAFE_WHERE_IN_CHUNK = 2000;

    private function normalizarFilial(?string $filial): ?string
    {
        $filial = trim((string) $filial);
        if ($filial === '' || strtolower($filial) === 'todas') {
            return null;
        }

        return $filial;
    }

    private function listarIntermediadores()
    {
        return DB::table('Intermediador')
            ->select(['Nome conhecido as nome'])
            ->whereNotNull('Nome conhecido')
            ->where('Nome conhecido', '<>', '')
            ->orderBy('Nome conhecido')
            ->get();
    }

    private function resolverPeriodoExportacao(Request $request): array
    {
        $dataInicio = $request->get('data_inicio');
        $dataFim = $request->get('data_fim');
        $ano = $this->parseIntegerInRange($request->get('ano'), 2000, 2100);
        $mes = $this->parseIntegerInRange($request->get('mes'), 1, 12);
        $mesInicio = $this->parseIntegerInRange($request->get('mes_inicio'), 1, 12);
        $mesFim = $this->parseIntegerInRange($request->get('mes_fim'), 1, 12);

        if ($ano && $mes) {
            return [
                Carbon::create($ano, $mes, 1)->startOfMonth()->format('Y-m-d'),
                Carbon::create($ano, $mes, 1)->endOfMonth()->format('Y-m-d'),
            ];
        }

        if ($ano && $mesInicio && $mesFim) {
            if ($mesInicio > $mesFim) {
                return [null, null];
            }

            return [
                Carbon::create($ano, $mesInicio, 1)->startOfMonth()->format('Y-m-d'),
                Carbon::create($ano, $mesFim, 1)->endOfMonth()->format('Y-m-d'),
            ];
        }

        return [$dataInicio, $dataFim];
    }

    private function parseIntegerInRange(mixed $value, int $min, int $max): ?int
    {
        $value = trim((string) $value);

        if ($value === '' || ! preg_match('/^\d+$/', $value)) {
            return null;
        }

        $intValue = (int) $value;

        if ($intValue < $min || $intValue > $max) {
            return null;
        }

        return $intValue;
    }

    private function normalizarMesFiltro(mixed $mes): ?int
    {
        return $this->parseIntegerInRange($mes, 1, 12);
    }

    private function aplicarFiltroMes($query, string $column, ?int $mes): void
    {
        if ($mes) {
            $query->whereMonth($column, $mes);
        }
    }

    private function normalizarBanco(?string $banco): ?string
    {
        $banco = preg_replace('/\D+/', '', trim((string) $banco));

        if ($banco === '' || ! in_array($banco, self::BANCOS_VALIDOS, true)) {
            return null;
        }

        return $banco;
    }

    private function aplicarFiltroBanco($query, ?string $banco): void
    {
        if (! $banco) {
            return;
        }

        // Normaliza valores do banco (ex: "33", "033 ", etc.) para comparação consistente.
        $query->whereRaw(
            "RIGHT('000' + LTRIM(RTRIM(CAST([Codigocedente] AS VARCHAR(20)))), 3) = ?",
            [$banco]
        );
    }

    private function correspondeBancoFiltro($codigoCedente, string $banco): bool
    {
        $codigoCedente = preg_replace('/\D+/', '', trim((string) $codigoCedente));
        if ($codigoCedente === '') {
            return false;
        }

        return str_pad($codigoCedente, 3, '0', STR_PAD_LEFT) === $banco;
    }

    public function index(Request $request)
    {
        $filtros = $request->only([
            'pagamento',
            'data_inicio',
            'data_fim',
            'origem',
            'filial',
            'somente_atrasados',
            'somente_pagas',
            'sort',
            'direction',
            'intermediador',
            'documento',
            'codigo_cliente',
            'numero',
            'parcela',
            'banco',
            'valor_de',
            'valor_ate',
            'valor_pago_de',
            'valor_pago_ate',
            'data_emissao_inicio',
            'data_emissao_fim',
            'data_venc_inicio',
            'data_venc_fim',
            'data_pagto_inicio',
            'data_pagto_fim',
        ]);

        $pendenciasUnificadas = $this->buscarPendenciasGlobal($filtros);

        // --- 5. Paginação Manual ---
        $perPage = (int) $request->input('perPage', 50);
        $page = (int) $request->input('page', 1);
        $total = $pendenciasUnificadas->count();

        $pendenciasInfo = new \Illuminate\Pagination\LengthAwarePaginator(
            $pendenciasUnificadas->forPage($page, $perPage),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $intermediadores = \Illuminate\Support\Facades\DB::table('Intermediador')
            ->select(['Nome conhecido as nome'])
            ->orderBy('Nome conhecido')
            ->get();

        $selectedClientName = $request->codigo_cliente ?? '';
        if ($request->filled('codigo_cliente') && is_numeric($request->codigo_cliente)) {
            $selectedClientName = DB::table('Clientes')
                ->where('Codigo cliente', $request->codigo_cliente)
                ->value('Nome conhecido') ?? $request->codigo_cliente;
        }

        $totalGeral = $pendenciasUnificadas->sum('valor');
        $totalGeralPago = $pendenciasUnificadas->sum('valor_pago');

        return view('financeiro.pendencias.index', [
            'pendencias' => $pendenciasInfo,
            'totalGeral' => $totalGeral,
            'totalGeralPago' => $totalGeralPago,
            'filtros' => $filtros,
            'intermediadores' => $intermediadores,
            'selectedClientName' => $selectedClientName,
        ]);
    }

    public function pollBradesco()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('bradesco:poll-boletos');
            $output = \Illuminate\Support\Facades\Artisan::output();

            return response()->json([
                'success' => true,
                'message' => 'Sincronização com Bradesco finalizada.',
                'details' => $output,
            ]);
        } catch (\Exception $e) {
            Log::error('[BRADESCO POLL] Erro na execução manual: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao sincronizar com Bradesco: '.$e->getMessage(),
            ], 500);
        }
    }

    public function gerarPdf(Request $request)
    {
        // Aumenta o tempo e memória limite para geração de PDfs grandes
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '1024M');

        $filtros = $request->only([
            'pagamento',
            'data_inicio',
            'data_fim',
            'origem',
            'filial',
            'somente_atrasados',
            'somente_pagas',
            'sort',
            'direction',
            'intermediador',
            'documento',
            'codigo_cliente',
            'numero',
            'parcela',
            'banco',
            'valor_de',
            'valor_ate',
            'valor_pago_de',
            'valor_pago_ate',
            'data_emissao_inicio',
            'data_emissao_fim',
            'data_venc_inicio',
            'data_venc_fim',
            'data_pagto_inicio',
            'data_pagto_fim',
        ]);
        $pendenciasUnificadas = $this->buscarPendenciasGlobal($filtros);

        Log::info('[FINANCEIRO PDF] Registros encontrados: '.$pendenciasUnificadas->count(), [
            'filtros' => $filtros,
            'user_id' => auth()->id(),
        ]);

        // Ordenação condicional: por Data pagto se "somente pagas", senão por Vencimento
        $somentePagasFiltro = isset($filtros['somente_pagas']) && $filtros['somente_pagas'] == '1';
        $pendenciasUnificadas = $pendenciasUnificadas->sortBy(function ($item) use ($somentePagasFiltro) {
            if ($somentePagasFiltro) {
                return $item->data_pagto ? \Carbon\Carbon::parse($item->data_pagto)->format('Y-m-d') : '9999-12-31';
            }

            return $item->vencimento ? \Carbon\Carbon::parse($item->vencimento)->format('Y-m-d') : '9999-12-31';
        })->values();

        // Agrupa por Tipo - Numero para o PDF
        $pendenciasAgrupadas = $pendenciasUnificadas->groupBy(function ($item) {
            return $item->tipo_origem.' - '.$item->numero_origem;
        });

        $filial = Filial::first();

        $pdf = Pdf::loadView('financeiro.pendencias.pdf', compact('pendenciasAgrupadas', 'filial', 'filtros'));

        return $pdf->stream('contas-a-receber.pdf');
    }

    /**
     * Reaproveita a lógica de pendências para retornar apenas recebimentos DI já pagos.


     *


     * @param  bool  $aplicarLimite  Mantém comportamento legado (limit 2000) quando true.
     */

    /**
     * Reaproveita a lógica de pendências para retornar apenas recebimentos DI já pagos.
     *
     * @param  bool  $aplicarLimite  Mantém comportamento legado (limit 2000) quando true.
     */
    public function buscarRecebimentosPagosDi(Carbon $dataInicio, Carbon $dataFim, bool $aplicarLimite = true): Collection
    {
        $filtros = [
            'pagamento' => 'DI',
            'somente_pagas' => '1',
            'sort' => 'data_pagto',
            'direction' => 'asc',
            'data_pagto_inicio' => $dataInicio->toDateTimeString(),
            'data_pagto_fim' => $dataFim->toDateTimeString(),
        ];

        $registros = $this->buscarPendenciasGlobal($filtros, $aplicarLimite);

        $vendasU = $registros->map(function ($item) {
            $valorPago = (float) (($item->model->{'Valor pago'} ?? 0));
            $valorLancamento = $valorPago > 0 ? $valorPago : (float) ($item->valor ?? 0);
            $dataPagto = $item->data_pagto ? Carbon::parse($item->data_pagto) : null;

            $link = $item->tipo_origem === 'VENDA'
                ? route('vendas.create', ['cod_venda' => $item->id_origem])
                : route('ordens.create', ['cod_ordem' => $item->id_origem]);

            return (object) [
                'tipo_origem' => $item->tipo_origem,
                'numero_origem' => $item->numero_origem,
                'cliente_nome' => $item->cliente_nome,
                'parcela' => $item->parcela,
                'tipo_pagto' => $item->tipo_pagto,
                'data_pagto' => $dataPagto,
                'valor' => $valorLancamento,
                'link' => $link,
            ];
        });

        // Fetch GUARDOU where Valor entrada > 0 (Crédito)
        $guardouQuery = DB::table('Lancamento conta itens')
            ->where('Descricao conta', 'GUARDOU')
            ->where('Tipo pagto', 'DI')
            ->where('Valor entrada', '>', 0)
            ->whereBetween('Data pagto', [$dataInicio->toDateTimeString(), $dataFim->toDateTimeString()]);

        if ($aplicarLimite) {
            $guardouQuery->limit(2000);
        }

        $guardou = $guardouQuery->get()->map(function ($item) {
            return (object) [
                'tipo_origem' => 'GUARDOU',
                'numero_origem' => $item->Documento,
                'cliente_nome' => 'GUARDOU - ' . ($item->Observação ?: 'CAIXA INTERNO'),
                'parcela' => $item->Parcela,
                'tipo_pagto' => $item->{'Tipo pagto'},
                'data_pagto' => $item->{'Data pagto'} ? Carbon::parse($item->{'Data pagto'}) : null,
                'valor' => (float) $item->{'Valor entrada'},
                'link' => '#',
            ];
        });

        return $vendasU->concat($guardou)->filter(function ($item) use ($dataInicio, $dataFim) {
            return $item->data_pagto
                && $item->data_pagto->between($dataInicio->copy()->startOfDay(), $dataFim->copy()->endOfDay());
        })->values();
    }

    private function buscarPendenciasGlobal($filtros)
    {
        $pagamento = $filtros['pagamento'] ?? null;
        $dataInicio = $filtros['data_inicio'] ?? null;
        $dataFim = $filtros['data_fim'] ?? null;
        $origem = $filtros['origem'] ?? 'todos'; // todos, venda, os
        $filial = $filtros['filial'] ?? null;
        $somenteAtrasados = isset($filtros['somente_atrasados']) && $filtros['somente_atrasados'] == '1';
        $somentePagas = isset($filtros['somente_pagas']) && $filtros['somente_pagas'] == '1';
        $colunaDataVenda = 'Data vencto';
        $colunaDataOrdem = 'Data vencto';

        $intermediador = $filtros['intermediador'] ?? null;
        $documento = $filtros['documento'] ?? null;
        $codigoCliente = $filtros['codigo_cliente'] ?? null;
        $numero = $filtros['numero'] ?? null;
        $parcela = $filtros['parcela'] ?? null;
        $valorDe = $this->normalizarValorRetornoFiltro($filtros['valor_de'] ?? null);
        $valorAte = $this->normalizarValorRetornoFiltro($filtros['valor_ate'] ?? null);
        $valorPagoDe = $this->normalizarValorRetornoFiltro($filtros['valor_pago_de'] ?? null);
        $valorPagoAte = $this->normalizarValorRetornoFiltro($filtros['valor_pago_ate'] ?? null);
        $banco = $this->normalizarBanco($filtros['banco'] ?? null);

        $sort = $filtros['sort'] ?? 'vencimento'; // emissao, vencimento, valor
        $direction = $filtros['direction'] ?? 'desc'; // asc, desc

        $vendas = collect();
        $ordens = collect();
        $hoje = Carbon::today()->format('Y-m-d');

        // Range para Vencimento/Pagamento
        $vencInicio = $filtros['data_venc_inicio'] ?? $filtros['data_inicio'] ?? null;
        $vencFim = $filtros['data_venc_fim'] ?? $filtros['data_fim'] ?? null;

        // Range para Emissão
        $emissaoInicio = $filtros['data_emissao_inicio'] ?? null;
        $emissaoFim = $filtros['data_emissao_fim'] ?? null;
        
        // Range para Pagamento
        $pagtoInicio = $filtros['data_pagto_inicio'] ?? null;
        $pagtoFim = $filtros['data_pagto_fim'] ?? null;

        // --- VENDAS ---
        if ($origem === 'todos' || $origem === 'venda') {
            $vendasQuery = VendasPagto::with(['venda.cliente']);

            if ($somentePagas) {
                $vendasQuery->whereNotNull('Data pagto')->where('Valor pago', '>', 0);
            } else {
                $vendasQuery->where(function ($q) {
                    $q->whereNull('Valor pago')->orWhere('Valor pago', 0);
                })
                    ->whereNull('Data pagto');
            }

            if ($pagamento) {
                $vendasQuery->where('Tipo pagto', $pagamento);
            }

            if ($vencInicio) {
                $vendasQuery->where($colunaDataVenda, '>=', $vencInicio);
            }
            if ($vencFim) {
                $vendasQuery->where($colunaDataVenda, '<=', $vencFim);
            }
            if ($pagtoInicio) {
                $vendasQuery->where('Data pagto', '>=', $pagtoInicio);
            }
            if ($pagtoFim) {
                $vendasQuery->where('Data pagto', '<=', $pagtoFim);
            }

            if ($emissaoInicio) {
                $vendasQuery->whereHas('venda', function ($q) use ($emissaoInicio) {
                    $q->where('Data emissão', '>=', $emissaoInicio);
                });
            }
            if ($emissaoFim) {
                $vendasQuery->whereHas('venda', function ($q) use ($emissaoFim) {
                    $q->where('Data emissão', '<=', $emissaoFim);
                });
            }
            if ($somenteAtrasados) {
                $vendasQuery->where('Data vencto', '<', $hoje);
            }
            if ($filial) {
                $vendasQuery->whereHas('venda', function ($q) use ($filial) {
                    $q->where('Filial', $filial);
                });
            }

            $this->aplicarFiltroBanco($vendasQuery, $banco);

            // Novos Filtros Vendas
            if ($intermediador) {
                $vendasQuery->whereHas('venda', function ($q) use ($intermediador) {
                    if ($intermediador === '_ONLINE_') {
                        $q->whereNotNull('Intermediador')->where('Intermediador', '<>', '');
                    } else {
                        $q->where('Intermediador', 'like', '%'.$intermediador.'%');
                    }
                });
            }
            if ($codigoCliente) {
                if (is_numeric($codigoCliente)) {
                    $vendasQuery->whereHas('venda', function ($q) use ($codigoCliente) {
                        $q->where('Codigo cliente', $codigoCliente);
                    });
                } else {
                    $vendasQuery->whereHas('venda', function ($q) use ($codigoCliente) {
                        $q->where('Cliente', 'like', '%'.$codigoCliente.'%');
                    });
                }
            }
            if ($numero) {
                // 'No venda' is usually on VendasPagto too, but let's be safe and use relationship or try direct if known.
                // VendasPagto model usually has 'No venda'.
                $vendasQuery->where('No venda', 'like', '%'.$numero.'%');
            }
            if ($parcela) {
                $vendasQuery->where('Parcela pagto', 'like', '%'.$parcela.'%');
            }
            if ($documento) {
                $vendasQuery->whereHas('venda.cliente', function ($q) use ($documento) {
                    $q->where('Cpf', 'like', '%'.$documento.'%')
                        ->orWhere('Cnpj', 'like', '%'.$documento.'%');
                });
            }
            if ($valorDe !== null) {
                $vendasQuery->where('Valor', '>=', $valorDe);
            }
            if ($valorAte !== null) {
                $vendasQuery->where('Valor', '<=', $valorAte);
            }
            if ($valorPagoDe !== null) {
                $vendasQuery->where('Valor pago', '>=', $valorPagoDe);
            }
            if ($valorPagoAte !== null) {
                $vendasQuery->where('Valor pago', '<=', $valorPagoAte);
            }

            $vendas = $vendasQuery->limit(2000)->get();
            if ($banco) {
                $vendas = $vendas->filter(function ($v) use ($banco) {
                    return $this->correspondeBancoFiltro($v->{'Codigocedente'} ?? null, $banco);
                })->values();
            }
        }

        // --- ORDENS ---
        // Intermediador é exclusivo de vendas, então se houver filtro de intermediador, ignoramos OS?
        // O usuário disse: "Intermediador (Filtro exclusivo para vendas)".
        // Se o usuário preencher Intermediador, ele quer ver vendas com aquele intermediador.
        // Devemos mostrar OS? Provavelmente não, pois OS não tem intermediador.
        // Então se $intermediador estiver preenchido, $ordens deve ser vazio.
        if (($origem === 'todos' || $origem === 'os') && empty($intermediador)) {
            $ordensQuery = OrdemPagto::with(['ordem.clienteByCodigo']);

            if ($somentePagas) {
                $ordensQuery->whereNotNull('Data pgto')->where('Valor pago', '>', 0);
            } else {
                $ordensQuery->where(function ($q) {
                    $q->whereNull('Valor pago')->orWhere('Valor pago', 0);
                })
                    ->whereNull('Data pgto');
            }

            if ($pagamento) {
                $ordensQuery->where('Tipo pagto', $pagamento);
            }

            // Range para Vencimento/Pagamento
            if ($vencInicio) {
                $ordensQuery->where($colunaDataOrdem, '>=', $vencInicio);
            }
            if ($vencFim) {
                $ordensQuery->where($colunaDataOrdem, '<=', $vencFim);
            }
            if ($pagtoInicio) {
                $ordensQuery->where('Data pgto', '>=', $pagtoInicio);
            }
            if ($pagtoFim) {
                $ordensQuery->where('Data pgto', '<=', $pagtoFim);
            }

            // Range para Emissão
            if ($emissaoInicio) {
                $ordensQuery->whereHas('ordem', function ($q) use ($emissaoInicio) {
                    $q->where('Data emissao', '>=', $emissaoInicio);
                });
            }
            if ($emissaoFim) {
                $ordensQuery->whereHas('ordem', function ($q) use ($emissaoFim) {
                    $q->where('Data emissao', '<=', $emissaoFim);
                });
            }
            if ($somenteAtrasados) {
                $ordensQuery->where('Data vencto', '<', $hoje);
            }
            if ($filial) {
                $ordensQuery->whereHas('ordem', function ($q) use ($filial) {
                    $q->where('Filial', $filial);
                });
            }

            $this->aplicarFiltroBanco($ordensQuery, $banco);

            // Novos Filtros OS
            if ($codigoCliente) {
                if (is_numeric($codigoCliente)) {
                    $ordensQuery->whereHas('ordem', function ($q) use ($codigoCliente) {
                        $q->where('Codigo cliente', $codigoCliente);
                    });
                } else {
                    $ordensQuery->whereHas('ordem', function ($q) use ($codigoCliente) {
                        $q->where('Cliente', 'like', '%'.$codigoCliente.'%');
                    });
                }
            }
            if ($numero) {
                $ordensQuery->where('Numero ordem', 'like', '%'.$numero.'%');
            }
            if ($parcela) {
                $ordensQuery->where('Parcela', 'like', '%'.$parcela.'%');
            }
            if ($documento) {
                $ordensQuery->whereHas('ordem.clienteByCodigo', function ($q) use ($documento) {
                    $q->where('Cpf', 'like', '%'.$documento.'%')
                        ->orWhere('Cnpj', 'like', '%'.$documento.'%');
                });
            }
            if ($valorDe !== null) {
                $ordensQuery->where('Valor', '>=', $valorDe);
            }
            if ($valorAte !== null) {
                $ordensQuery->where('Valor', '<=', $valorAte);
            }
            if ($valorPagoDe !== null) {
                $ordensQuery->where('Valor pago', '>=', $valorPagoDe);
            }
            if ($valorPagoAte !== null) {
                $ordensQuery->where('Valor pago', '<=', $valorPagoAte);
            }

            $ordens = $ordensQuery->limit(2000)->get();
            if ($banco) {
                $ordens = $ordens->filter(function ($o) use ($banco) {
                    return $this->correspondeBancoFiltro($o->{'Codigocedente'} ?? null, $banco);
                })->values();
            }
        }

        $pendenciasUnificadas = collect();

        foreach ($vendas as $v) {
            $clienteNome = 'Cliente Desconhecido';
            if ($v->venda) {
                if ($v->venda->cliente) {
                    $clienteNome = $v->venda->cliente->{'Nome conhecido'};
                } elseif (! empty($v->venda->Cliente)) {
                    $clienteNome = $v->venda->Cliente;
                }
            }

            $clienteId = $v->venda ? $v->venda->{'Codigo cliente'} : null;

            $pendenciasUnificadas->push((object) [
                'tipo_origem' => 'VENDA',
                'numero_origem' => $v->{'No venda'},
                'id_origem' => $v->{'No venda'},
                'cliente_nome' => $clienteNome,
                'cliente_id' => $clienteId,
                'data_emissao' => $v->venda ? $v->venda->{'Data emissao'} : null,
                'emissao' => $v->venda ? $v->venda->{'Data emissao'} : null, // Alias para sort
                'parcela' => $v->{'Parcela pagto'},
                'vencimento' => $v->{'Data vencto'},
                'valor' => $v->Valor,
                'tipo_pagto' => $v->{'Tipo pagto'},
                'doc_conta' => $v->Boletonumero ?: $v->{'Nome conta'},
                'data_pagto' => $v->{'Data pagto'},
                'intermediador' => $v->venda ? $v->venda->Intermediador : null,
                'model' => $v,
                'valor_pago' => (float) ($v->{'Valor pago'} ?? 0),
            ]);
        }

        foreach ($ordens as $o) {
            $clienteNome = 'Cliente Desconhecido';
            if ($o->ordem) {
                if ($o->ordem->clienteByCodigo) {
                    $clienteNome = $o->ordem->clienteByCodigo->{'Nome conhecido'};
                } elseif (! empty($o->ordem->Cliente)) {
                    $clienteNome = $o->ordem->Cliente;
                }
            }

            $clienteId = $o->ordem ? $o->ordem->{'Codigo cliente'} : null;

            $pendenciasUnificadas->push((object) [
                'tipo_origem' => 'OS',
                'numero_origem' => $o->{'Numero ordem'},
                'id_origem' => $o->{'Numero ordem'},
                'cliente_nome' => $clienteNome,
                'cliente_id' => $clienteId,
                'data_emissao' => $o->ordem ? $o->ordem->{'Data emissao'} : null,
                'emissao' => $o->ordem ? $o->ordem->{'Data emissao'} : null,
                'parcela' => $o->Parcela,
                'vencimento' => $o->{'Data vencto'},
                'valor' => $o->Valor,
                'tipo_pagto' => $o->{'Tipo pagto'},
                'doc_conta' => $o->{'Numero ordem'},
                'data_pagto' => $o->{'Data pgto'},
                'intermediador' => null,
                'model' => $o,
                'valor_pago' => (float) ($o->{'Valor pago'} ?? 0),
            ]);
        }

        // Mapeamento de chaves do frontend para propriedades da Collection
        $sortMap = [
            'numero' => 'numero_origem',
            'cliente' => 'cliente_nome',
            'emissao' => 'emissao',
            'vencimento' => 'vencimento',
            'valor' => 'valor',
            'origem' => 'tipo_origem',
            'parcela' => 'parcela',
            'tipo_pagto' => 'tipo_pagto',
            'doc_conta' => 'doc_conta',
            'intermediador' => 'intermediador',
            'pagamento' => 'data_pagto',
        ];

        $sortBy = $sortMap[$sort] ?? $sort;

        // Ordenação
        if ($direction === 'asc') {
            return $pendenciasUnificadas->sortBy($sortBy);
        } else {
            return $pendenciasUnificadas->sortByDesc($sortBy);
        }
    }

    /**
     * OLD Relatório de Fechamento Financeiro (DEPRECATED - use index2() instead)
     * Kept for reference only
     */
    /*
    public function fechamento(Request $request)
    {
        $ano = $request->get('ano', date('Y'));

        // Fetch all data for single page
        $visaoGeral = $this->getVisaoGeralData($ano);
        $contabil = $this->getContabilData($ano);
        $despesas = $this->getDespesasData($ano);
        $comparativos = $this->getComparativosData($ano);
        $financeiro = $this->getFinanceiroData($ano);

        $data = array_merge(
            $visaoGeral,
            ['contabil_detalhado' => $contabil], // Avoid key collision if any
            ['despesas_detalhado' => $despesas],
            ['comparativos_detalhado' => $comparativos],
            ['financeiro_detalhado' => $financeiro],
            ['ano' => $ano]
        );

        return view('financeiro.fechamento.index', $data);
    }
    */

    /**
     * Define as condições WHERE para cada categoria de despesa
     * Retorna array de closures que podem ser aplicados em queries
     */
    private function getCategoryWhereConditions()
    {
        return [
            'Operacional' => function ($query) {
                $query->where(function ($q) {
                    $q->where('Código tipo', 4)
                        ->orWhere('Nome tipo', 'like', '%OPERACIONA%')
                        ->orWhere('Descricao conta', 'like', '%OPERACIONA%');
                });
            },
            'ADM' => function ($query) {
                $query->where(function ($q) {
                    $q->where('Código tipo', 34)
                        ->orWhere('Nome tipo', 'like', '%ADM%')
                        ->orWhere('Descricao conta', 'like', '%ADM%');
                });
            },
            'Diretoria' => function ($query) {
                $query->where(function ($q) {
                    $q->where('Código tipo', 50)
                        ->orWhere('Nome tipo', 'like', '%DIRETORIA%')
                        ->orWhere('Descricao conta', 'like', '%DIRETORIA%');
                });
            },
            'Alimenta / Limp' => function ($query) {
                $query->where(function ($q) {
                    $q->where('Código tipo', 49)
                        ->orWhere('Nome tipo', 'like', '%ALIMENTA%')
                        ->orWhere('Descricao conta', 'like', '%ALIMENTA%');
                });
            },
            'Veículo / Manut' => function ($query) {
                $query->where(function ($q) {
                    $q->whereIn('Código tipo', [13, 67])
                        ->orWhere('Nome tipo', 'like', '%VEICULO%')
                        ->orWhere('Nome tipo', 'like', '%MANUTENCAO%')
                        ->orWhere('Descricao conta', 'like', '%VEICULO%')
                        ->orWhere('Descricao conta', 'like', '%MANUTENCAO%');
                })->where('Descricao conta', '!=', 'COMBUSTIVEL');
            },
            'Sites e MKT' => function ($query) {
                $query->where(function ($q) {
                    $q->where('Código tipo', 51)
                        ->orWhere('Nome tipo', 'like', '%E-COMMERCE / MKT%')
                        ->orWhere('Descricao conta', 'like', '%E-COMMERCE / MKT%');
                });
            },
        ];
    }

    private function applyImpostoClause($query): void
    {
        $query->where('Código tipo', 6);
    }

    private function applyFolhaClause($query): void
    {
        $query->where(function ($q) {
            $q->where('Nome tipo', 'like', '%FOLHA%')
              ->orWhere('Descricao conta', 'like', '%FOLHA%');
        });
    }

    public function getVisaoGeralData($ano, $filial = null, $mes = null)
    {
        $filialSelecionada = $this->normalizarFilial($filial);
        $mesSelecionado = $this->normalizarMesFiltro($mes);
        $mesesLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        // === CÁLCULO DE RECEITAS E DESPESAS REAIS ===

        // 1. RECEITAS TOTAIS (Vendas + Ordens PAGAS)
        $receitaVendasQuery = VendasPagto::whereNotNull('Data pagto')
            ->where('Valor pago', '>', 0)
            ->whereYear('Data pagto', $ano);
        $this->aplicarFiltroMes($receitaVendasQuery, 'Data pagto', $mesSelecionado);
        if ($filialSelecionada) {
            $receitaVendasQuery->whereHas('venda', function ($q) use ($filialSelecionada) {
                $q->where('Filial', $filialSelecionada);
            });
        }
        $receitaVendas = $receitaVendasQuery->sum(DB::raw('ABS([Valor pago])'));

        $receitaOrdensQuery = OrdemPagto::whereNotNull('Data pgto')
            ->where('Valor pago', '>', 0)
            ->whereYear('Data pgto', $ano);
        $this->aplicarFiltroMes($receitaOrdensQuery, 'Data pgto', $mesSelecionado);
        if ($filialSelecionada) {
            $receitaOrdensQuery->whereHas('ordem', function ($q) use ($filialSelecionada) {
                $q->where('Filial', $filialSelecionada);
            });
        }
        $receitaOrdens = $receitaOrdensQuery->sum(DB::raw('ABS([Valor pago])'));

        $receitaTotal = $receitaVendas + $receitaOrdens;

        // 2. DESPESAS TOTAIS (Fornecedores + Operacionais + Impostos + Folha)
        $despesaFornecedoresQuery = EntradaPecaPagto::whereNotNull('Data pagto')
            ->whereYear('Data pagto', $ano);
        $this->aplicarFiltroMes($despesaFornecedoresQuery, 'Data pagto', $mesSelecionado);
        if ($filialSelecionada) {
            $despesaFornecedoresQuery->whereHas('entrada', function ($q) use ($filialSelecionada) {
                $q->where('Filial', $filialSelecionada);
            });
        }
        $despesaFornecedores = $despesaFornecedoresQuery->sum(DB::raw('ABS([Valor pago])'));

        $categories = $this->getCategoryWhereConditions();
        $despesaOperacionais = 0;
        foreach ($categories as $whereCondition) {
            $query = DB::table('Lancamento conta itens')->whereNotNull('Data pagto')
                ->whereYear('Data pagto', $ano)
                ->when($filialSelecionada, fn ($q) => $q->where('Filial', $filialSelecionada));
            $this->aplicarFiltroMes($query, 'Data pagto', $mesSelecionado);
            $despesaOperacionais += $query
                ->where($whereCondition)
                ->sum(DB::raw('ABS([Valor pago])'));
        }

        $despesaImpostosQuery = DB::table('Lancamento conta itens')->whereNotNull('Data pagto')
            ->whereYear('Data pagto', $ano)
            ->when($filialSelecionada, fn ($q) => $q->where('Filial', $filialSelecionada))
            ->where(function ($q) {
                $this->applyImpostoClause($q);
            });
        $this->aplicarFiltroMes($despesaImpostosQuery, 'Data pagto', $mesSelecionado);
        $despesaImpostos = $despesaImpostosQuery->sum(DB::raw('ABS([Valor pago])'));

        $despesaFolhaQuery = DB::table('Lancamento conta itens')->whereNotNull('Data pagto')
            ->whereYear('Data pagto', $ano)
            ->when($filialSelecionada, fn ($q) => $q->where('Filial', $filialSelecionada))
            ->where(function ($q) {
                $this->applyFolhaClause($q);
            });
        $this->aplicarFiltroMes($despesaFolhaQuery, 'Data pagto', $mesSelecionado);
        $despesaFolha = $despesaFolhaQuery->sum(DB::raw('ABS([Valor pago])'));

        $despesaTotal = $despesaFornecedores + $despesaOperacionais + $despesaImpostos + $despesaFolha;
        $lucroLiquido = $receitaTotal - $despesaTotal;
        $margemLucro = $receitaTotal > 0 ? ($lucroLiquido / $receitaTotal) * 100 : 0;

        // 3. KPIs
        $kpis = [
            [
                'title' => 'Receita Total',
                'value' => $receitaTotal,
                'trend' => 'up',
                'trendValue' => $mesSelecionado ? ($mesesLabels[$mesSelecionado - 1].'/'.$ano) : ('Ano '.$ano),
                'subtitle' => 'vendas + serviços',
                'variant' => 'success',
                'icon' => 'TrendingUp',
            ],
            [
                'title' => 'Despesa Total',
                'value' => $despesaTotal,
                'trend' => 'down',
                'trendValue' => $receitaTotal > 0
                    ? number_format(($despesaTotal / $receitaTotal) * 100, 1, ',', '.').'%'
                    : '0,0%',
                'subtitle' => 'da receita',
                'variant' => 'danger',
                'icon' => 'TrendingDown',
            ],
            [
                'title' => 'Lucro Líquido',
                'value' => $lucroLiquido,
                'trend' => $lucroLiquido >= 0 ? 'up' : 'down',
                'trendValue' => number_format($margemLucro, 1, ',', '.').'%',
                'subtitle' => 'margem líquida',
                'variant' => $lucroLiquido >= 0 ? 'success' : 'danger',
                'icon' => 'Wallet',
            ],
        ];

        // 4. RECEITAS MENSAIS (Receitas vs Despesas por mês)
        $receitasMensal = [];
        for ($mes = 1; $mes <= 12; $mes++) {
            if ($mesSelecionado && $mes !== $mesSelecionado) {
                $receitasMensal[] = [
                    'mes' => $mesesLabels[$mes - 1],
                    'receitas' => 0,
                    'despesas' => 0,
                ];

                continue;
            }

            $receitaMesVendasQuery = VendasPagto::whereNotNull('Data pagto')
                ->where('Valor pago', '>', 0)
                ->whereYear('Data pagto', $ano)
                ->whereMonth('Data pagto', $mes);
            if ($filialSelecionada) {
                $receitaMesVendasQuery->whereHas('venda', function ($q) use ($filialSelecionada) {
                    $q->where('Filial', $filialSelecionada);
                });
            }
            $receitaMes = $receitaMesVendasQuery->sum(DB::raw('ABS([Valor pago])'));

            $receitaMesOrdensQuery = OrdemPagto::whereNotNull('Data pgto')
                ->where('Valor pago', '>', 0)
                ->whereYear('Data pgto', $ano)
                ->whereMonth('Data pgto', $mes);
            if ($filialSelecionada) {
                $receitaMesOrdensQuery->whereHas('ordem', function ($q) use ($filialSelecionada) {
                    $q->where('Filial', $filialSelecionada);
                });
            }
            $receitaMes += $receitaMesOrdensQuery->sum(DB::raw('ABS([Valor pago])'));

            $despesaMesFornecedoresQuery = EntradaPecaPagto::whereNotNull('Data pagto')
                ->whereYear('Data pagto', $ano)
                ->whereMonth('Data pagto', $mes);
            if ($filialSelecionada) {
                $despesaMesFornecedoresQuery->whereHas('entrada', function ($q) use ($filialSelecionada) {
                    $q->where('Filial', $filialSelecionada);
                });
            }
            $despesaMes = $despesaMesFornecedoresQuery->sum(DB::raw('ABS([Valor pago])'));

            foreach ($categories as $whereCondition) {
                $despesaMes += DB::table('Lancamento conta itens')->whereNotNull('Data pagto')
                    ->whereYear('Data pagto', $ano)
                    ->whereMonth('Data pagto', $mes)
                    ->when($filialSelecionada, fn ($q) => $q->where('Filial', $filialSelecionada))
                    ->where($whereCondition)
                    ->sum(DB::raw('ABS([Valor pago])'));
            }

            $despesaMes += DB::table('Lancamento conta itens')->whereNotNull('Data pagto')
                ->whereYear('Data pagto', $ano)
                ->whereMonth('Data pagto', $mes)
                ->when($filialSelecionada, fn ($q) => $q->where('Filial', $filialSelecionada))
                ->where(function ($q) {
                    $q->where(function ($qImp) {
                        $this->applyImpostoClause($qImp);
                    })->orWhere(function ($qFol) {
                        $this->applyFolhaClause($qFol);
                    });
                })
                ->sum(DB::raw('ABS([Valor pago])'));

            $receitasMensal[] = [
                'mes' => $mesesLabels[$mes - 1],
                'receitas' => round($receitaMes, 2),
                'despesas' => round($despesaMes, 2),
            ];
        }

        // 5. RECEITAS POR CATEGORIA (Vendas vs Serviços)
        $totalVendasQuery = VendasPagto::whereNotNull('Data pagto')
            ->where('Valor pago', '>', 0)
            ->whereYear('Data pagto', $ano);
        $this->aplicarFiltroMes($totalVendasQuery, 'Data pagto', $mesSelecionado);
        if ($filialSelecionada) {
            $totalVendasQuery->whereHas('venda', function ($q) use ($filialSelecionada) {
                $q->where('Filial', $filialSelecionada);
            });
        }
        $totalVendas = $totalVendasQuery->sum(DB::raw('ABS([Valor pago])'));

        $totalServicosQuery = OrdemPagto::whereNotNull('Data pgto')
            ->where('Valor pago', '>', 0)
            ->whereYear('Data pgto', $ano);
        $this->aplicarFiltroMes($totalServicosQuery, 'Data pgto', $mesSelecionado);
        if ($filialSelecionada) {
            $totalServicosQuery->whereHas('ordem', function ($q) use ($filialSelecionada) {
                $q->where('Filial', $filialSelecionada);
            });
        }
        $totalServicos = $totalServicosQuery->sum(DB::raw('ABS([Valor pago])'));

        $totalGeral = $totalVendas + $totalServicos;

        $receitasCategorias = [
            [
                'nome' => 'Vendas',
                'faturado' => $totalVendas,
                'partGeral' => $totalGeral > 0 ? round(($totalVendas / $totalGeral) * 100, 1) : 0,
                'color' => 'hsl(160, 60%, 45%)',
            ],
            [
                'nome' => 'Serviços',
                'faturado' => $totalServicos,
                'partGeral' => $totalGeral > 0 ? round(($totalServicos / $totalGeral) * 100, 1) : 0,
                'color' => 'hsl(210, 60%, 50%)',
            ],
        ];

        // 6. DESPESAS OPERACIONAIS (já calculadas anteriormente)
        $despesasTipos = [];
        $totalDespesasOp = 0;

        foreach ($categories as $nomeCategoria => $whereCondition) {
            $query = DB::table('Lancamento conta itens')->whereNotNull('Data pagto')
                ->whereYear('Data pagto', $ano)
                ->when($filialSelecionada, fn ($q) => $q->where('Filial', $filialSelecionada));
            $this->aplicarFiltroMes($query, 'Data pagto', $mesSelecionado);
            $valor = $query
                ->where($whereCondition)
                ->sum(DB::raw('ABS([Valor pago])'));

            $totalDespesasOp += $valor;

            $despesasTipos[] = [
                'tipo' => $nomeCategoria,
                'valor' => round($valor, 2),
                'part' => 0, // Será calculado após
            ];
        }

        // Calcular percentuais
        foreach ($despesasTipos as &$item) {
            $item['part'] = $totalDespesasOp > 0
                ? round(($item['valor'] / $totalDespesasOp) * 100, 1)
                : 0;
        }
        unset($item);

        // Adicionar aviso sobre Comissão MKT
        $despesasTipos[] = [
            'tipo' => 'Comissão MKT',
            'valor' => 0,
            'part' => 0,
            'aviso' => 'Disponível após contratação do serviço Arquivei',
        ];

        // 7. RESUMO CONTÁBIL (já calculado em getFinanceiroData)
        $contabil = $this->getFinanceiroData($ano, $filialSelecionada, $mesSelecionado)['contabil'];

        return compact('kpis', 'receitasMensal', 'receitasCategorias', 'despesasTipos', 'contabil', 'mesesLabels');
    }

    public function getContabilData($ano, $filial = null, $mes = null)
    {
        $filialSelecionada = $this->normalizarFilial($filial);
        $mesSelecionado = $this->normalizarMesFiltro($mes);
        // =====================================================================
        // CFOPs de peças (Notasnf)
        // =====================================================================
        $cfopsNotasnf = [5405, 5102, 6102, 6108, 6404, 5117, 5656, 6110, 6117, 6119, 6403, 6656];

        // =====================================================================
        // BLOCO 1 – Peças Vendas
        //   Pedido  → Vendas [No nf]=0  (valor-desconto)
        //   Faturado/Qtd → Notasnf [Tipo notanf]='VENDA' + filtros CFOPs
        // =====================================================================
        $pedidoVendasMensal = array_fill(0, 12, 0);
        $faturadoVendasMensal = array_fill(0, 12, 0);
        $qtdVendasMensal = array_fill(0, 12, 0);

        for ($mes = 1; $mes <= 12; $mes++) {
            if ($mesSelecionado && $mes !== $mesSelecionado) {
                continue;
            }
            // Pedido
            $pedidoVendasMensal[$mes - 1] = (float) DB::table('Vendas')
                ->whereYear('Data emissão', $ano)
                ->whereMonth('Data emissão', $mes)
                ->when($filialSelecionada, fn ($q) => $q->where('Filial', $filialSelecionada))
                ->where('No nf', 0)
                ->sum(DB::raw('ISNULL([Total valor] - [Desconto], 0)'));

            // Faturado / Qtd
            $notasnfVendas = DB::table('Notasnf')
                ->whereYear('Data emissao nfe', $ano)
                ->whereMonth('Data emissao nfe', $mes)
                ->when($filialSelecionada, fn ($q) => $q->where('Filial', $filialSelecionada))
                ->where('Tipo notanf', 'VENDA')
                ->where('Cancelada', 0)
                ->where('Remessa', 0)
                ->where('Denegada', 0)
                ->where('Nfe entrega', 0)
                ->where('Imobilizado', 0)
                ->selectRaw('COUNT(*) as qtd, ISNULL(SUM([Valor total nf]), 0) as faturado')
                ->first();

            $faturadoVendasMensal[$mes - 1] = (float) ($notasnfVendas->faturado ?? 0);
            $qtdVendasMensal[$mes - 1] = (int) ($notasnfVendas->qtd ?? 0);
        }

        // =====================================================================
        // BLOCO 2 – Peças Ordens
        //   Sem linha Pedido
        //   Faturado/Qtd → Notasnf [Tipo notanf] != 'VENDA' + filtros CFOPs
        // =====================================================================
        $faturadoOrdensMensal = array_fill(0, 12, 0);
        $qtdOrdensMensal = array_fill(0, 12, 0);

        for ($mes = 1; $mes <= 12; $mes++) {
            if ($mesSelecionado && $mes !== $mesSelecionado) {
                continue;
            }
            $notasnfOrdens = DB::table('Notasnf')
                ->whereYear('Data emissao nfe', $ano)
                ->whereMonth('Data emissao nfe', $mes)
                ->when($filialSelecionada, fn ($q) => $q->where('Filial', $filialSelecionada))
                ->where('Tipo notanf', '!=', 'VENDA')
                ->whereIn('Código natureza', $cfopsNotasnf)
                ->where('Cancelada', 0)
                ->where('Remessa', 0)
                ->where('Denegada', 0)
                ->where('Nfe entrega', 0)
                ->where('Imobilizado', 0)
                ->selectRaw('COUNT(*) as qtd, ISNULL(SUM([Valor total nf]), 0) as faturado')
                ->first();

            $faturadoOrdensMensal[$mes - 1] = (float) ($notasnfOrdens->faturado ?? 0);
            $qtdOrdensMensal[$mes - 1] = (int) ($notasnfOrdens->qtd ?? 0);
        }

        // =====================================================================
        // BLOCO 3 – Serviços Ordens
        //   Faturado/Qtd → tabela local servicos_importacao_itens (tecnoar_api)
        // =====================================================================
        $faturadoServicosMensal = array_fill(0, 12, 0);
        $qtdServicosMensal = array_fill(0, 12, 0);

        for ($mes = 1; $mes <= 12; $mes++) {
            if ($mesSelecionado && $mes !== $mesSelecionado) {
                continue;
            }
            $servicos = ServicosImportacaoItem::on('tecnoar_api')
                ->whereYear('data', $ano)
                ->whereMonth('data', $mes)
                ->selectRaw('COUNT(*) as qtd, ISNULL(SUM(valor_faturado), 0) as faturado')
                ->first();

            $faturadoServicosMensal[$mes - 1] = (float) ($servicos->faturado ?? 0);
            $qtdServicosMensal[$mes - 1] = (int) ($servicos->qtd ?? 0);
        }

        // =====================================================================
        // BLOCO 4 – Despesas Compras
        //   Sem linha Pedido
        //   Faturado/Qtd → Entradas peças + JOIN Natureza operação + CFOPs
        // =====================================================================
        $cfopsEntradas = ['1102', '1403', '1652', '2102', '2403', '2652'];

        $faturadoComprasMensal = array_fill(0, 12, 0);
        $qtdComprasMensal = array_fill(0, 12, 0);

        for ($mes = 1; $mes <= 12; $mes++) {
            if ($mesSelecionado && $mes !== $mesSelecionado) {
                continue;
            }
            $compras = DB::table('Entradas peças as E')
                ->join('Natureza operação as N', 'E.Natureza operação', '=', 'N.Descrição natureza')
                ->whereYear('E.Data entrada', $ano)
                ->whereMonth('E.Data entrada', $mes)
                ->when($filialSelecionada, fn ($q) => $q->where('E.Filial', $filialSelecionada))
                ->whereIn('N.Código natureza', $cfopsEntradas)
                ->selectRaw('COUNT(*) as qtd, ISNULL(SUM(E.[Valor total nota]), 0) as faturado')
                ->first();

            $faturadoComprasMensal[$mes - 1] = (float) ($compras->faturado ?? 0);
            $qtdComprasMensal[$mes - 1] = (int) ($compras->qtd ?? 0);
        }

        // =====================================================================
        // Helpers: calcular total anual e ticket médio mensal
        // =====================================================================
        $calcTicketMedio = function (array $faturado, array $qtd): array {
            $tickets = [];
            foreach ($faturado as $idx => $f) {
                $tickets[] = ($qtd[$idx] ?? 0) > 0 ? round($f / $qtd[$idx], 2) : 0;
            }

            return $tickets;
        };

        $mediaAnual = function (array $tickets): float {
            $positivos = array_filter($tickets, fn ($v) => $v > 0);

            return count($positivos) > 0 ? array_sum($positivos) / count($positivos) : 0;
        };

        // =====================================================================
        // Construção do $contabilData no formato esperado pela blade
        // =====================================================================
        $ticketVendas = $calcTicketMedio($faturadoVendasMensal, $qtdVendasMensal);
        $ticketOrdens = $calcTicketMedio($faturadoOrdensMensal, $qtdOrdensMensal);
        $ticketServicos = $calcTicketMedio($faturadoServicosMensal, $qtdServicosMensal);
        $ticketCompras = $calcTicketMedio($faturadoComprasMensal, $qtdComprasMensal);

        $contabilData = [
            'receitas' => [
                // Bloco 1 – Peças Vendas (Pedido + Faturado + Qtd)
                'pecas_vendas' => [
                    'rows' => [
                        'Pedido' => [
                            'mensal' => $pedidoVendasMensal,
                            'total_anual' => array_sum($pedidoVendasMensal),
                        ],
                        'Faturado' => [
                            'mensal' => $faturadoVendasMensal,
                            'total_anual' => array_sum($faturadoVendasMensal),
                        ],
                        'Qtd' => [
                            'mensal' => $qtdVendasMensal,
                            'total_anual' => array_sum($qtdVendasMensal),
                        ],
                    ],
                    'footer' => [
                        'ticket_medio' => [
                            'mensal' => $ticketVendas,
                            'media_anual' => $mediaAnual($ticketVendas),
                        ],
                    ],
                ],
                // Bloco 2 – Peças Ordens (sem Pedido)
                'pecas_ordens' => [
                    'rows' => [
                        'Faturado' => [
                            'mensal' => $faturadoOrdensMensal,
                            'total_anual' => array_sum($faturadoOrdensMensal),
                        ],
                        'Qtd' => [
                            'mensal' => $qtdOrdensMensal,
                            'total_anual' => array_sum($qtdOrdensMensal),
                        ],
                    ],
                    'footer' => [
                        'ticket_medio' => [
                            'mensal' => $ticketOrdens,
                            'media_anual' => $mediaAnual($ticketOrdens),
                        ],
                    ],
                ],
                // Bloco 3 – Serviços Ordens (sem Pedido)
                'servicos_ordens' => [
                    'rows' => [
                        'Faturado' => [
                            'mensal' => $faturadoServicosMensal,
                            'total_anual' => array_sum($faturadoServicosMensal),
                        ],
                        'Qtd' => [
                            'mensal' => $qtdServicosMensal,
                            'total_anual' => array_sum($qtdServicosMensal),
                        ],
                    ],
                    'footer' => [
                        'ticket_medio' => [
                            'mensal' => $ticketServicos,
                            'media_anual' => $mediaAnual($ticketServicos),
                        ],
                    ],
                ],
            ],
            'despesas' => [
                // Bloco 4 – Despesas Compras (sem Pedido)
                'compras' => [
                    'rows' => [
                        'Faturado' => [
                            'mensal' => $faturadoComprasMensal,
                            'total_anual' => array_sum($faturadoComprasMensal),
                        ],
                        'Qtd' => [
                            'mensal' => $qtdComprasMensal,
                            'total_anual' => array_sum($qtdComprasMensal),
                        ],
                    ],
                    'footer' => [
                        'ticket_medio' => [
                            'mensal' => $ticketCompras,
                            'media_anual' => $mediaAnual($ticketCompras),
                        ],
                    ],
                ],
            ],
        ];

        // =====================================================================
        // Pizza (gráfico de distribuição de receitas)
        // =====================================================================
        $totalVendas = array_sum($faturadoVendasMensal);
        $totalOrdens = array_sum($faturadoOrdensMensal);
        $totalServicos = array_sum($faturadoServicosMensal);

        $contabilPizza = [
            'series' => [$totalVendas, $totalOrdens, $totalServicos],
            'labels' => ['Vendas', 'Ordens - Peças', 'Ordens - Serviços'],
        ];

        $totalFaturadoGeral = $totalVendas + $totalOrdens + $totalServicos;
        $totalQtdGeral = array_sum($qtdVendasMensal) + array_sum($qtdOrdensMensal) + array_sum($qtdServicosMensal);
        $ticketMedioGeral = $totalQtdGeral > 0 ? round($totalFaturadoGeral / $totalQtdGeral, 2) : 0;

        $receitasData = [
            'somatorio' => [
                'faturado' => $totalFaturadoGeral,
                'qtd' => $totalQtdGeral,
                'ticketMedio' => $ticketMedioGeral,
            ],
        ];

        $pizza = $contabilPizza;

        return compact('contabilData', 'pizza', 'receitasData');
    }

    private function getOperacionaisDetalhado($ano, $filial = null, $mes = null)
    {
        $filialSelecionada = $this->normalizarFilial($filial);
        $mesSelecionado = $this->normalizarMesFiltro($mes);
        $categories = $this->getCategoryWhereConditions();
        $operacionaisDetalhado = [];
        $totalGeralAnual = 0;

        foreach ($categories as $nomeCategoria => $whereCondition) {
            // Query agrupada por mês para esta categoria
            $dadosMensais = DB::table('Lancamento conta itens')->selectRaw('MONTH([Data pagto]) as mes, SUM(ABS([Valor pago])) as total')
                ->whereNotNull('Data pagto')
                ->whereYear('Data pagto', $ano)
                ->when($filialSelecionada, fn ($q) => $q->where('Filial', $filialSelecionada))
                ->where($whereCondition)
                ->when($mesSelecionado, fn ($q) => $q->whereMonth('Data pagto', $mesSelecionado))
                ->groupBy(DB::raw('MONTH([Data pagto])'))
                ->pluck('total', 'mes');

            // Preencher array de 12 meses (1-12)
            $mensal = [];
            for ($mes = 1; $mes <= 12; $mes++) {
                $mensal[] = round($dadosMensais->get($mes, 0), 2);
            }

            $totalAnual = array_sum($mensal);
            $totalGeralAnual += $totalAnual;

            $operacionaisDetalhado[] = [
                'grupo' => $nomeCategoria,
                'mensal' => $mensal,
                'total_anual' => $totalAnual,
                'share_anual' => 0, // Será calculado depois
            ];
        }

        // Calcular percentuais
        foreach ($operacionaisDetalhado as &$item) {
            $item['share_anual'] = $totalGeralAnual > 0
                ? round(($item['total_anual'] / $totalGeralAnual) * 100, 1)
                : 0;
        }

        return ['itens' => $operacionaisDetalhado];
    }

    private function getFinanceirasDetalhado($ano, $filial = null, $mes = null)
    {
        $filialSelecionada = $this->normalizarFilial($filial);
        $mesSelecionado = $this->normalizarMesFiltro($mes);
        $financeirasDetalhado = [];
        $totalGeralAnual = 0;

        // 1. FORNECEDORES - da tabela Entradas peças pagtos
        $fornecedoresMensais = EntradaPecaPagto::selectRaw('MONTH([Data pagto]) as mes, SUM(ABS([Valor pago])) as total')
            ->whereNotNull('Data pagto')
            ->whereYear('Data pagto', $ano)
            ->when($filialSelecionada, function ($q) use ($filialSelecionada) {
                $q->whereHas('entrada', function ($q2) use ($filialSelecionada) {
                    $q2->where('Filial', $filialSelecionada);
                });
            })
            ->when($mesSelecionado, fn ($q) => $q->whereMonth('Data pagto', $mesSelecionado))
            ->groupBy(DB::raw('MONTH([Data pagto])'))
            ->pluck('total', 'mes');

        $mensalFornecedores = [];
        for ($mes = 1; $mes <= 12; $mes++) {
            $mensalFornecedores[] = round($fornecedoresMensais->get($mes, 0), 2);
        }
        $totalFornecedores = array_sum($mensalFornecedores);
        $totalGeralAnual += $totalFornecedores;

        $financeirasDetalhado[] = [
            'grupo' => 'Fornecedores',
            'mensal' => $mensalFornecedores,
            'total_anual' => $totalFornecedores,
            'share_anual' => 0,
        ];

        // 2. OPERACIONAIS - soma de todas as categorias operacionais
        $categories = $this->getCategoryWhereConditions();
        $operacionaisMensais = array_fill(0, 12, 0);

        foreach ($categories as $nomeCategoria => $whereCondition) {
            $dadosMensais = DB::table('Lancamento conta itens')->selectRaw('MONTH([Data pagto]) as mes, SUM(ABS([Valor pago])) as total')
                ->whereNotNull('Data pagto')
                ->whereYear('Data pagto', $ano)
                ->when($filialSelecionada, fn ($q) => $q->where('Filial', $filialSelecionada))
                ->where($whereCondition)
                ->when($mesSelecionado, fn ($q) => $q->whereMonth('Data pagto', $mesSelecionado))
                ->groupBy(DB::raw('MONTH([Data pagto])'))
                ->pluck('total', 'mes');

            for ($mes = 1; $mes <= 12; $mes++) {
                $operacionaisMensais[$mes - 1] += $dadosMensais->get($mes, 0);
            }
        }

        $operacionaisMensais = array_map(function ($val) {
            return round($val, 2);
        }, $operacionaisMensais);
        $totalOperacionais = array_sum($operacionaisMensais);
        $totalGeralAnual += $totalOperacionais;

        $financeirasDetalhado[] = [
            'grupo' => 'Operacionais',
            'mensal' => $operacionaisMensais,
            'total_anual' => $totalOperacionais,
            'share_anual' => 0,
        ];

        // 3. IMPOSTOS - Nome tipo LIKE 'IMPOSTO'
        $impostosMensais = DB::table('Lancamento conta itens')->selectRaw('MONTH([Data pagto]) as mes, SUM(ABS([Valor pago])) as total')
            ->whereNotNull('Data pagto')
            ->whereYear('Data pagto', $ano)
            ->when($filialSelecionada, fn ($q) => $q->where('Filial', $filialSelecionada))
            ->where(function ($q) {
                $this->applyImpostoClause($q);
            })
            ->when($mesSelecionado, fn ($q) => $q->whereMonth('Data pagto', $mesSelecionado))
            ->groupBy(DB::raw('MONTH([Data pagto])'))
            ->pluck('total', 'mes');

        $mensalImpostos = [];
        for ($mes = 1; $mes <= 12; $mes++) {
            $mensalImpostos[] = round($impostosMensais->get($mes, 0), 2);
        }
        $totalImpostos = array_sum($mensalImpostos);
        $totalGeralAnual += $totalImpostos;

        $financeirasDetalhado[] = [
            'grupo' => 'Impostos',
            'mensal' => $mensalImpostos,
            'total_anual' => $totalImpostos,
            'share_anual' => 0,
        ];

        // 4. FOLHA - Nome tipo LIKE 'FOLHA'
        $folhaMensais = DB::table('Lancamento conta itens')->selectRaw('MONTH([Data pagto]) as mes, SUM(ABS([Valor pago])) as total')
            ->whereNotNull('Data pagto')
            ->whereYear('Data pagto', $ano)
            ->when($filialSelecionada, fn ($q) => $q->where('Filial', $filialSelecionada))
            ->where(function ($q) {
                $this->applyFolhaClause($q);
            })
            ->when($mesSelecionado, fn ($q) => $q->whereMonth('Data pagto', $mesSelecionado))
            ->groupBy(DB::raw('MONTH([Data pagto])'))
            ->pluck('total', 'mes');

        $mensalFolha = [];
        for ($mes = 1; $mes <= 12; $mes++) {
            $mensalFolha[] = round($folhaMensais->get($mes, 0), 2);
        }
        $totalFolha = array_sum($mensalFolha);
        $totalGeralAnual += $totalFolha;

        $financeirasDetalhado[] = [
            'grupo' => 'Folha',
            'mensal' => $mensalFolha,
            'total_anual' => $totalFolha,
            'share_anual' => 0,
        ];

        \Log::info('Após Folha', ['count' => count($financeirasDetalhado), 'grupos' => array_column($financeirasDetalhado, 'grupo')]);

        // Calcular percentuais
        foreach ($financeirasDetalhado as &$item) {
            $item['share_anual'] = $totalGeralAnual > 0
                ? round(($item['total_anual'] / $totalGeralAnual) * 100, 1)
                : 0;
        }
        unset($item); // CRÍTICO: Destruir referência para evitar bugs

        // Calcular totais mensais
        $totaisMensais = array_fill(0, 12, 0);
        foreach ($financeirasDetalhado as $item) {
            for ($mes = 0; $mes < 12; $mes++) {
                $totaisMensais[$mes] += $item['mensal'][$mes];
            }
        }

        return [
            'itens' => $financeirasDetalhado,
            'totais_mensais' => $totaisMensais,
            'total_geral_anual' => $totalGeralAnual,
        ];
    }

    public function getDespesasData($ano, $filial = null, $mes = null)
    {
        $despesasData = [
            'financeiras' => $this->getFinanceirasDetalhado($ano, $filial, $mes),
            'operacionais_detalhado' => $this->getOperacionaisDetalhado($ano, $filial, $mes),
            'comissao_mkt' => [
                'fixas' => [
                    ['nome' => 'Mensalidade B2B', 'valor' => 120.00],
                    ['nome' => 'Mensalidade PluggTo', 'valor' => 350.00],
                    ['nome' => 'Quality (SEO) – GOAT – Conta Azul', 'valor' => 850.00],
                    ['nome' => 'Google', 'valor' => 150.00],
                    ['nome' => 'Facebook', 'valor' => 100.00],
                    ['nome' => 'Email Marketing (LocaWeb)', 'valor' => 85.00],
                    ['nome' => 'Integra', 'valor' => 120.00],
                    ['nome' => 'Frenet', 'valor' => 95.00],
                    ['nome' => 'Outros', 'valor' => 48.80],
                ],
                'variaveis' => [
                    ['nome' => 'Google ADW', 'valor' => 1500.00],
                    ['nome' => 'E-mail Marketing (Lead Lovers)', 'valor' => 450.00],
                    ['nome' => 'Campanha ML', 'valor' => 1200.00],
                    ['nome' => 'Verba Marketing', 'valor' => 1500.00],
                    ['nome' => 'Outros', 'valor' => 147.00],
                ],
                'comissoes' => [
                    ['nome' => 'CNOVA', 'valor' => 850.00],
                    ['nome' => 'Magazine Luiza', 'valor' => 1200.00],
                    ['nome' => 'Carrefour', 'valor' => 450.00],
                    ['nome' => 'B2W', 'valor' => 950.00],
                    ['nome' => 'Mercado Livre', 'valor' => 4500.00],
                    ['nome' => 'MadeiraMadeira', 'valor' => 750.00],
                    ['nome' => 'Amazon', 'valor' => 1100.00],
                    ['nome' => 'Leroy Merlin', 'valor' => 650.00],
                    ['nome' => 'Shopee', 'valor' => 1800.00],
                    ['nome' => 'Shophub', 'valor' => 350.00],
                    ['nome' => 'Outros (Aliexpress, Webcontinental)', 'valor' => 1200.00],
                ],
                'resumo' => [
                    'subtotal_fixas' => 1918.80,
                    'subtotal_variaveis' => 4797.00,
                    'subtotal_comissoes' => 13800.00,
                    'total_geral' => 20515.80,
                    'pie_chart' => [10, 25, 65],
                ],
            ],
        ];

        // Recalculate totals for financeiras
        $despesasData['financeiras']['total_geral_anual'] = array_sum($despesasData['financeiras']['totais_mensais']);
        foreach ($despesasData['financeiras']['itens'] as &$item) {
            $item['total_anual'] = array_sum($item['mensal']);
            $item['share_anual'] = ($despesasData['financeiras']['total_geral_anual'] ?? 0) > 0
                ? (($item['total_anual'] / $despesasData['financeiras']['total_geral_anual']) * 100)
                : 0;
        }

        return compact('despesasData');
    }

    public function getComparativosData($ano, $filial = null, $mes = null)
    {
        $mesSelecionado = $this->normalizarMesFiltro($mes);
        $comparativosData = [
            'fatGeral' => ['meta' => 1500000, 'janeiro' => 128500],
            'composicao' => ['servicos' => 30, 'comercial' => 70, 'maoDeObra' => 40, 'pecas' => 60],
        ];

        $mesesLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $crescimentoMensal = [];
        foreach ($mesesLabels as $index => $mesLabel) {
            $valor = rand(-5, 20);
            if ($mesSelecionado && ($index + 1) !== $mesSelecionado) {
                $valor = 0;
            }

            $crescimentoMensal[] = ['mes' => $mesLabel, 'valor' => $valor];
        }

        return compact('comparativosData', 'crescimentoMensal');
    }

    public function getFinanceiroData($ano, $filial = null, $mes = null)
    {
        $filialSelecionada = $this->normalizarFilial($filial);
        // Calcular dados reais por filial
        $contabil = $this->getRelatorioFinanceiroPorFilial($ano, $filialSelecionada, $mes);

        $mesesLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        // Buscar recebimentos reais de Vendas e Ordens
        $entradasData = $this->getEntradasPorTipoPagamento($ano, $filialSelecionada, $mes);
        $entradasData['meses'] = $mesesLabels;

        return compact('contabil', 'entradasData');
    }

    /**
     * Calcula relatório financeiro detalhado por filial
     */
    private function getRelatorioFinanceiroPorFilial($ano, $filial = null, $mes = null)
    {
        $filialSelecionada = $this->normalizarFilial($filial);
        $mesSelecionado = $this->normalizarMesFiltro($mes);
        $filiais = $filialSelecionada ? [$filialSelecionada] : ['Tecnoar Matriz', 'Tecnoar Filial'];
        $resultado = [];

        foreach ($filiais as $filial) {
            // 1. RECEITAS (Vendas + Ordens PAGAS)
            $receitasVendas = VendasPagto::whereNotNull('Data pagto')
                ->where('Valor pago', '>', 0)
                ->whereYear('Data pagto', $ano)
                ->when($mesSelecionado, fn ($q) => $q->whereMonth('Data pagto', $mesSelecionado))
                ->whereHas('venda', function ($q) use ($filial) {
                    $q->where('Filial', $filial);
                })
                ->sum(DB::raw('ABS([Valor pago])'));

            $receitasOrdens = OrdemPagto::whereNotNull('Data pgto')
                ->where('Valor pago', '>', 0)
                ->whereYear('Data pgto', $ano)
                ->when($mesSelecionado, fn ($q) => $q->whereMonth('Data pgto', $mesSelecionado))
                ->whereHas('ordem', function ($q) use ($filial) {
                    $q->where('Filial', $filial);
                })
                ->sum(DB::raw('ABS([Valor pago])'));

            $totalReceitas = $receitasVendas + $receitasOrdens;

            // 2. FORNECEDORES (Entradas peças pagtos)
            // Nota: Filial está em 'Entradas peças', não em 'Entradas peças pagtos'
            $fornecedores = EntradaPecaPagto::whereNotNull('Data pagto')
                ->whereYear('Data pagto', $ano)
                ->when($mesSelecionado, fn ($q) => $q->whereMonth('Data pagto', $mesSelecionado))
                ->whereHas('entrada', function ($q) use ($filial) {
                    $q->where('Filial', $filial);
                })
                ->sum(DB::raw('ABS([Valor pago])'));

            // 3. OPERACIONAIS (soma de todas as categorias operacionais)
            $categories = $this->getCategoryWhereConditions();
            $operacionais = 0;

            foreach ($categories as $whereCondition) {
                $valor = DB::table('Lancamento conta itens')->whereNotNull('Data pagto')
                    ->whereYear('Data pagto', $ano)
                    ->when($mesSelecionado, fn ($q) => $q->whereMonth('Data pagto', $mesSelecionado))
                    ->where('Filial', $filial)
                    ->where($whereCondition)
                    ->sum(DB::raw('ABS([Valor pago])'));

                $operacionais += $valor;
            }

            // 4. IMPOSTOS
            $impostos = DB::table('Lancamento conta itens')->whereNotNull('Data pagto')
                ->whereYear('Data pagto', $ano)
                ->when($mesSelecionado, fn ($q) => $q->whereMonth('Data pagto', $mesSelecionado))
                ->where('Filial', $filial)
                ->where(function ($q) {
                    $this->applyImpostoClause($q);
                })
                ->sum(DB::raw('ABS([Valor pago])'));

            // 5. FOLHA
            $folha = DB::table('Lancamento conta itens')->whereNotNull('Data pagto')
                ->whereYear('Data pagto', $ano)
                ->when($mesSelecionado, fn ($q) => $q->whereMonth('Data pagto', $mesSelecionado))
                ->where('Filial', $filial)
                ->where(function ($q) {
                    $this->applyFolhaClause($q);
                })
                ->sum(DB::raw('ABS([Valor pago])'));

            // RESULTADO = Receitas - (Fornecedores + Operacionais + Impostos + Folha)
            $totalDespesas = $fornecedores + $operacionais + $impostos + $folha;
            $situacaoReal = $totalReceitas - $totalDespesas;

            $nomeEmpresa = $filial === 'Tecnoar Matriz' ? 'Matriz' : ($filial === 'Tecnoar Filial' ? 'Filial' : $filial);

            $resultado[] = [
                'empresa' => $nomeEmpresa,
                'situacao' => $situacaoReal >= 0 ? 'LUCRO' : 'PREJUÍZO',
                'vendaServico' => $totalReceitas,
                'compra' => $fornecedores,
                'despesa' => $operacionais,
                'imposto' => $impostos,
                'folha' => $folha,
                'situacaoReal' => $situacaoReal,
            ];
        }

        return $resultado;
    }

    /**
     * Busca recebimentos (valores pagos) de Vendas e Ordens agrupados por tipo de pagamento
     */
    private function getEntradasPorTipoPagamento($ano, $filial = null, $mes = null)
    {
        $filialSelecionada = $this->normalizarFilial($filial);
        $mesSelecionado = $this->normalizarMesFiltro($mes);
        // Mapeamento de tipos de pagamento para categorias
        $tiposPagamento = [
            'Boleto (BO)' => ['BO'],
            'Cheque (CH)' => ['CH'],
            'Dinheiro (DI)' => ['DI'],
            'Deposito (DC / PI)' => ['DC', 'PI'],
            'Cartões (CC / CD)' => ['CC', 'CD'],
            'Geral internet (TB)' => ['TB'],
        ];

        $rows = [];
        $qtdPorCategoria = [];
        $totalMensal = array_fill(0, 12, 0);
        $totalGeralAno = 0;
        $totalQtdAno = 0;

        foreach ($tiposPagamento as $categoria => $tipos) {
            $mensalCategoria = array_fill(0, 12, 0);
            $qtdCategoria = 0;

            // 1. Buscar de VendasPagto
            foreach ($tipos as $tipo) {
                $vendasPagtos = VendasPagto::selectRaw('MONTH([Data pagto]) as mes, SUM(ABS([Valor pago])) as total')
                    ->whereNotNull('Data pagto')
                    ->where('Valor pago', '>', 0)
                    ->whereYear('Data pagto', $ano)
                    ->where('Tipo pagto', $tipo)
                    ->when($mesSelecionado, fn ($q) => $q->whereMonth('Data pagto', $mesSelecionado))
                    ->when($filialSelecionada, function ($q) use ($filialSelecionada) {
                        $q->whereHas('venda', function ($q2) use ($filialSelecionada) {
                            $q2->where('Filial', $filialSelecionada);
                        });
                    })
                    ->groupBy(DB::raw('MONTH([Data pagto])'))
                    ->pluck('total', 'mes');

                foreach ($vendasPagtos as $mes => $valor) {
                    $mensalCategoria[$mes - 1] += $valor;
                }

                $qtdCategoria += VendasPagto::whereNotNull('Data pagto')
                    ->where('Valor pago', '>', 0)
                    ->whereYear('Data pagto', $ano)
                    ->where('Tipo pagto', $tipo)
                    ->when($mesSelecionado, fn ($q) => $q->whereMonth('Data pagto', $mesSelecionado))
                    ->when($filialSelecionada, function ($q) use ($filialSelecionada) {
                        $q->whereHas('venda', function ($q2) use ($filialSelecionada) {
                            $q2->where('Filial', $filialSelecionada);
                        });
                    })
                    ->count();
            }

            // 2. Buscar de OrdemPagto
            foreach ($tipos as $tipo) {
                $ordensPagtos = OrdemPagto::selectRaw('MONTH([Data pgto]) as mes, SUM(ABS([Valor pago])) as total')
                    ->whereNotNull('Data pgto')
                    ->where('Valor pago', '>', 0)
                    ->whereYear('Data pgto', $ano)
                    ->where('Tipo pagto', $tipo)
                    ->when($mesSelecionado, fn ($q) => $q->whereMonth('Data pgto', $mesSelecionado))
                    ->when($filialSelecionada, function ($q) use ($filialSelecionada) {
                        $q->whereHas('ordem', function ($q2) use ($filialSelecionada) {
                            $q2->where('Filial', $filialSelecionada);
                        });
                    })
                    ->groupBy(DB::raw('MONTH([Data pgto])'))
                    ->pluck('total', 'mes');

                foreach ($ordensPagtos as $mes => $valor) {
                    $mensalCategoria[$mes - 1] += $valor;
                }

                $qtdCategoria += OrdemPagto::whereNotNull('Data pgto')
                    ->where('Valor pago', '>', 0)
                    ->whereYear('Data pgto', $ano)
                    ->where('Tipo pagto', $tipo)
                    ->when($mesSelecionado, fn ($q) => $q->whereMonth('Data pgto', $mesSelecionado))
                    ->when($filialSelecionada, function ($q) use ($filialSelecionada) {
                        $q->whereHas('ordem', function ($q2) use ($filialSelecionada) {
                            $q2->where('Filial', $filialSelecionada);
                        });
                    })
                    ->count();
            }

            // Calcular total da categoria
            $totalCategoria = array_sum($mensalCategoria);
            $totalGeralAno += $totalCategoria;
            $totalQtdAno += $qtdCategoria;

            // Acumular totais mensais
            for ($i = 0; $i < 12; $i++) {
                $totalMensal[$i] += $mensalCategoria[$i];
            }

            $rows[$categoria] = $mensalCategoria;
            $qtdPorCategoria[$categoria] = $qtdCategoria;
        }

        // Processar rows com percentuais
        $processedRows = [];
        foreach ($rows as $tipo => $valores) {
            $rowTotal = array_sum($valores);
            $share = $totalGeralAno > 0 ? round(($rowTotal / $totalGeralAno) * 100, 1) : 0;

            $mesesPreenchidos = count(array_filter($valores, function ($valor) {
                return $valor > 0;
            }));

            $processedRows[$tipo] = [
                'mensal' => $valores,
                'total' => $rowTotal,
                'share' => $share,
                'ticket_medio' => $mesesPreenchidos > 0 ? round($rowTotal / $mesesPreenchidos, 2) : 0,
            ];
        }

        $mesesPreenchidosGeral = count(array_filter($totalMensal, function ($valor) {
            return $valor > 0;
        }));

        return [
            'rows' => $rows,
            'processed_rows' => $processedRows,
            'total_mensal' => $totalMensal,
            'total_geral_ano' => $totalGeralAno,
            'ticket_medio_geral' => $mesesPreenchidosGeral > 0 ? round($totalGeralAno / $mesesPreenchidosGeral, 2) : 0,
        ];
    }

    /**
     * Exportar despesas para auditoria em formato CSV
     */
    public function exportarDespesas(Request $request)
    {
        $categoria = $request->get('categoria');
        [$dataInicio, $dataFim] = $this->resolverPeriodoExportacao($request);
        $filial = $this->normalizarFilial($request->get('filial'));

        // Validação
        if (! $dataInicio || ! $dataFim) {
            return back()->with('error', 'Período é obrigatório');
        }

        $categories = $this->getCategoryWhereConditions();

        if ($categoria && ! isset($categories[$categoria])) {
            return back()->with('error', 'Categoria inválida');
        }

        // Buscar TODOS os registros e campos (SELECT *)
        $query = DB::table('Lancamento conta itens')->whereNotNull('Data pagto')
            ->whereBetween('Data pagto', [$dataInicio, $dataFim]);

        if ($filial) {
            $query->where('Filial', $filial);
        }

        if ($categoria) {
            $query->where($categories[$categoria]);
        } else {
            // Sem categoria: exportar todas as categorias operacionais
            $query->where(function ($q) use ($categories) {
                foreach ($categories as $whereCondition) {
                    $q->orWhere($whereCondition);
                }
            });
        }

        $registros = $query->orderBy('Data pagto', 'desc')->get();

        // Gerar CSV
        $categoriaLabel = $categoria ?: 'todas_categorias';
        $filename = 'despesas_'.str_replace(' ', '_', strtolower($categoriaLabel)).'_'.date('Y-m-d').'.xlsx';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($registros, $categoria, $dataInicio, $dataFim, $filial) {
            $file = fopen('php://output', 'w');

            // BOM para UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Cabeçalho informativo
            fputcsv($file, ['Auditoria de Despesas - '.($categoria ?: 'Todas as categorias')]);
            fputcsv($file, ['Período: '.date('d/m/Y', strtotime($dataInicio)).' a '.date('d/m/Y', strtotime($dataFim))]);
            if ($filial) {
                fputcsv($file, ['Filtro de Filial aplicado: '.$filial]);
            }
            fputcsv($file, ['Gerado em: '.date('d/m/Y H:i:s')]);
            fputcsv($file, ['Total de Registros: '.count($registros)]);
            fputcsv($file, []); // Linha vazia

            // Cabeçalhos das colunas (pegar dinamicamente do primeiro registro)
            if (count($registros) > 0) {
                $primeiroRegistro = $registros->first();
                $colunas = $this->getRecordAttributeKeys($primeiroRegistro);
                fputcsv($file, $colunas);

                // Dados - todos os campos
                foreach ($registros as $reg) {
                    $linha = [];
                    foreach ($colunas as $coluna) {
                        $valor = $reg->{$coluna};

                        // Formatar datas
                        if (in_array($coluna, ['Data pagto', 'Data lancamento', 'Data vencto', 'Data emissão']) && $valor) {
                            try {
                                $linha[] = date('d/m/Y', strtotime($valor));
                            } catch (\Exception $e) {
                                $linha[] = $valor;
                            }
                        }
                        // Formatar valores numéricos
                        elseif (in_array($coluna, ['Valor entrada', 'Valor saida', 'Valor pago']) && is_numeric($valor)) {
                            $linha[] = number_format(abs($valor), 2, ',', '.');
                        }
                        // Outros campos
                        else {
                            $linha[] = $valor ?? '';
                        }
                    }
                    fputcsv($file, $linha);
                }
            } else {
                fputcsv($file, ['Nenhum registro encontrado para os filtros selecionados']);
            }

            fclose($file);
        };

        return $this->downloadExcelFromCsvCallback($callback, $filename);
    }

    /**
     * Exportar despesas financeiras para auditoria em formato CSV
     */
    public function exportarDespesasFinanceiras(Request $request)
    {
        $categoria = $request->get('categoria');
        [$dataInicio, $dataFim] = $this->resolverPeriodoExportacao($request);
        $filial = $this->normalizarFilial($request->get('filial'));

        // Validação
        if (! $dataInicio || ! $dataFim) {
            return back()->with('error', 'Período é obrigatório');
        }

        // Buscar registros baseado na categoria
        $registros = collect();

        switch ($categoria) {
            case 'Fornecedores':
                // Tabela: Entradas peças pagtos
                $registros = EntradaPecaPagto::with('entrada')->whereNotNull('Data pagto')
                    ->whereBetween('Data pagto', [$dataInicio, $dataFim])
                    ->when($filial, function ($q) use ($filial) {
                        $q->whereHas('entrada', function ($q2) use ($filial) {
                            $q2->where('Filial', $filial);
                        });
                    })
                    ->orderBy('Data pagto', 'desc')
                    ->get();
                break;

            case 'Operacionais':
                // Soma de todas as categorias operacionais
                $categories = $this->getCategoryWhereConditions();
                $query = DB::table('Lancamento conta itens')->whereNotNull('Data pagto')
                    ->whereBetween('Data pagto', [$dataInicio, $dataFim]);

                if ($filial) {
                    $query->where('Filial', $filial);
                }

                // Aplicar OR entre todas as categorias
                $query->where(function ($q) use ($categories) {
                    foreach ($categories as $whereCondition) {
                        $q->orWhere($whereCondition);
                    }
                });

                $registros = $query->orderBy('Data pagto', 'desc')->get();
                break;

            case 'Impostos':
                // Nome tipo LIKE 'IMPOSTO'
                $registros = DB::table('Lancamento conta itens')->whereNotNull('Data pagto')
                    ->whereBetween('Data pagto', [$dataInicio, $dataFim])
                    ->where(function ($q) {
                        $this->applyImpostoClause($q);
                    })
                    ->when($filial, fn ($q) => $q->where('Filial', $filial))
                    ->orderBy('Data pagto', 'desc')
                    ->get();
                break;

            case 'Folha':
                // Nome tipo LIKE 'FOLHA' ou Descricao conta LIKE 'FOLHA'
                $registros = DB::table('Lancamento conta itens')->whereNotNull('Data pagto')
                    ->whereBetween('Data pagto', [$dataInicio, $dataFim])
                    ->where(function ($q) {
                        $this->applyFolhaClause($q);
                    })
                    ->when($filial, fn ($q) => $q->where('Filial', $filial))
                    ->orderBy('Data pagto', 'desc')
                    ->get();
                break;

            default:
                if ($categoria) {
                    return back()->with('error', 'Categoria inválida');
                }

                // Sem categoria: exportar todas as categorias financeiras
                $fornecedores = EntradaPecaPagto::with('entrada')->whereNotNull('Data pagto')
                    ->whereBetween('Data pagto', [$dataInicio, $dataFim])
                    ->when($filial, function ($q) use ($filial) {
                        $q->whereHas('entrada', function ($q2) use ($filial) {
                            $q2->where('Filial', $filial);
                        });
                    })
                    ->orderBy('Data pagto', 'desc')
                    ->get();

                $categories = $this->getCategoryWhereConditions();
                $lancamentos = DB::table('Lancamento conta itens')->whereNotNull('Data pagto')
                    ->whereBetween('Data pagto', [$dataInicio, $dataFim])
                    ->when($filial, fn ($q) => $q->where('Filial', $filial))
                    ->where(function ($q) use ($categories) {
                        $q->where(function ($q2) use ($categories) {
                            foreach ($categories as $whereCondition) {
                                $q2->orWhere($whereCondition);
                            }
                        });
                        $q->orWhere(function ($q3) {
                            $this->applyImpostoClause($q3);
                        });
                        $q->orWhere(function ($q4) {
                            $this->applyFolhaClause($q4);
                        });
                    })
                    ->orderBy('Data pagto', 'desc')
                    ->get();

                $registros = $fornecedores->concat($lancamentos)->values();
        }

        $codigosFornecedores = $registros
            ->filter(fn ($reg) => $reg instanceof EntradaPecaPagto && ! empty($reg->entrada?->{'Codigo fornecedor'}))
            ->map(fn ($reg) => $reg->entrada->{'Codigo fornecedor'})
            ->unique()
            ->values()
            ->all();

        $fornecedoresMap = ! empty($codigosFornecedores)
            ? Fornecedor::whereIn('Codigo fornecedor', $codigosFornecedores)->get()->keyBy('Codigo fornecedor')
            : collect();

        // Gerar CSV
        $categoriaLabel = $categoria ?: 'todas_categorias';
        $filename = 'despesas_financeiras_'.str_replace(' ', '_', strtolower($categoriaLabel)).'_'.date('Y-m-d').'.xlsx';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($registros, $categoria, $dataInicio, $dataFim, $fornecedoresMap, $filial) {
            $file = fopen('php://output', 'w');
            $incluirClienteDocumento = $categoria === 'Fornecedores';

            // BOM para UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Cabeçalho informativo
            fputcsv($file, ['Auditoria de Despesas Financeiras - '.($categoria ?: 'Todas as categorias')]);
            fputcsv($file, ['Período: '.date('d/m/Y', strtotime($dataInicio)).' a '.date('d/m/Y', strtotime($dataFim))]);
            if ($filial) {
                fputcsv($file, ['Filtro de Filial aplicado: '.$filial]);
            }
            fputcsv($file, ['Gerado em: '.date('d/m/Y H:i:s')]);
            fputcsv($file, ['Total de Registros: '.count($registros)]);
            fputcsv($file, []); // Linha vazia

            // Cabeçalhos das colunas (união dinâmica para suportar múltiplas fontes)
            if (count($registros) > 0) {
                $colunas = [];
                foreach ($registros as $reg) {
                    $attrs = $this->getRecordAttributeKeys($reg);
                    foreach ($attrs as $attr) {
                        if (! in_array($attr, $colunas, true)) {
                            $colunas[] = $attr;
                        }
                    }
                }
                if ($incluirClienteDocumento) {
                    if (! empty($colunas)) {
                        $primeiraColuna = array_shift($colunas);
                        $colunas = array_merge([$primeiraColuna, 'Nome Cliente', 'Documento Cliente'], $colunas);
                    } else {
                        $colunas = ['Nome Cliente', 'Documento Cliente'];
                    }
                }
                fputcsv($file, $colunas);

                // Dados - todos os campos
                foreach ($registros as $reg) {
                    $nomeCliente = '';
                    $documentoCliente = '';

                    if ($reg instanceof EntradaPecaPagto) {
                        $entrada = $reg->entrada;
                        $codigoFornecedor = $entrada?->{'Codigo fornecedor'};
                        $fornecedor = $codigoFornecedor ? ($fornecedoresMap[$codigoFornecedor] ?? null) : null;

                        $nomeCliente = $this->nomeFornecedor($fornecedor) ?: ($entrada?->Fornecedor ?? '');
                        $documentoCliente = $this->documentoFornecedor($fornecedor);
                    }

                    $linha = [];
                    foreach ($colunas as $coluna) {
                        if ($incluirClienteDocumento && $coluna === 'Nome Cliente') {
                            $linha[] = $nomeCliente;

                            continue;
                        }
                        if ($incluirClienteDocumento && $coluna === 'Documento Cliente') {
                            $linha[] = $documentoCliente;

                            continue;
                        }

                        $valor = $reg->{$coluna};

                        // Formatar datas
                        if (in_array($coluna, ['Data pagto', 'Data lancamento', 'Data vencto', 'Data emissão']) && $valor) {
                            try {
                                $linha[] = date('d/m/Y', strtotime($valor));
                            } catch (\Exception $e) {
                                $linha[] = $valor;
                            }
                        }
                        // Formatar valores numéricos
                        elseif (in_array($coluna, ['Valor entrada', 'Valor saida', 'Valor pago', 'Valor']) && is_numeric($valor)) {
                            $linha[] = number_format(abs($valor), 2, ',', '.');
                        }
                        // Outros campos
                        else {
                            $linha[] = $valor ?? '';
                        }
                    }
                    fputcsv($file, $linha);
                }
            } else {
                fputcsv($file, ['Nenhum registro encontrado para os filtros selecionados']);
            }

            fclose($file);
        };

        return $this->downloadExcelFromCsvCallback($callback, $filename);
    }

    /**
     * Exportar recebimentos (pagamentos recebidos) para auditoria em formato CSV
     */
    public function exportarRecebimentos(Request $request)
    {
        $tipoPagto = $request->get('tipo_pagto');
        $intermediador = trim((string) $request->get('intermediador', ''));
        [$dataInicio, $dataFim] = $this->resolverPeriodoExportacao($request);
        $filial = $this->normalizarFilial($request->get('filial'));
        $filtrarIntermediadorTb = $tipoPagto === 'TB' && $intermediador !== '' && strtolower($intermediador) !== 'todos';

        // Validação
        if (! $tipoPagto || ! $dataInicio || ! $dataFim) {
            return back()->with('error', 'Tipo de pagamento e período são obrigatórios');
        }

        // Tipos podem vir separados por vírgula (ex: "DC,PI" ou "CC,CD")
        $tipos = explode(',', $tipoPagto);

        // Buscar registros de VendasPagto e OrdemPagto
        $registrosVendas = VendasPagto::with(['venda.cliente'])->whereNotNull('Data pagto')
            ->where('Valor pago', '>', 0)
            ->whereBetween('Data pagto', [$dataInicio, $dataFim])
            ->whereIn('Tipo pagto', $tipos)
            ->when($filial, function ($q) use ($filial) {
                $q->whereHas('venda', function ($q2) use ($filial) {
                    $q2->where('Filial', $filial);
                });
            })
            ->when($filtrarIntermediadorTb, function ($q) use ($intermediador) {
                $q->whereHas('venda', function ($q2) use ($intermediador) {
                    $q2->where('Intermediador', $intermediador);
                });
            })
            ->orderBy('Data pagto', 'desc')
            ->get();

        $registrosOrdens = $filtrarIntermediadorTb
            ? collect()
            : OrdemPagto::with(['ordem.clienteByCodigo'])->whereNotNull('Data pgto')
                ->where('Valor pago', '>', 0)
                ->whereBetween('Data pgto', [$dataInicio, $dataFim])
                ->whereIn('Tipo pagto', $tipos)
                ->when($filial, function ($q) use ($filial) {
                    $q->whereHas('ordem', function ($q2) use ($filial) {
                        $q2->where('Filial', $filial);
                    });
                })
                ->orderBy('Data pgto', 'desc')
                ->get();

        // Gerar CSV
        $tipoLabel = str_replace(',', '_', $tipoPagto);
        $filename = "recebimentos_{$tipoLabel}_".date('Y-m-d').'.xlsx';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($registrosVendas, $registrosOrdens, $tipoPagto, $intermediador, $dataInicio, $dataFim, $filial, $filtrarIntermediadorTb) {
            $file = fopen('php://output', 'w');

            // BOM para UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Cabeçalho informativo
            fputcsv($file, ['Auditoria de Recebimentos - Tipo: '.$tipoPagto]);
            fputcsv($file, ['Período: '.date('d/m/Y', strtotime($dataInicio)).' a '.date('d/m/Y', strtotime($dataFim))]);
            if ($filial) {
                fputcsv($file, ['Filtro de Filial aplicado: '.$filial]);
            }
            if ($tipoPagto === 'TB') {
                fputcsv($file, ['Filtro de Intermediador: '.($filtrarIntermediadorTb ? $intermediador : 'Todos')]);
            }
            fputcsv($file, ['Gerado em: '.date('d/m/Y H:i:s')]);
            fputcsv($file, ['Total de Registros: '.($registrosVendas->count() + $registrosOrdens->count())]);
            fputcsv($file, []); // Linha vazia

            // VENDAS
            if ($registrosVendas->count() > 0) {
                fputcsv($file, ['=== VENDAS ===']);
                fputcsv($file, []);

                $primeiroRegistro = $registrosVendas->first();
                $attrs = array_keys($primeiroRegistro->getAttributes());
                $primeiraColuna = array_shift($attrs);
                $colunas = array_merge([$primeiraColuna, 'Nome Cliente', 'Documento Cliente', 'Intermediador'], $attrs);
                fputcsv($file, $colunas);

                foreach ($registrosVendas as $reg) {
                    $venda = $reg->venda;
                    $cliente = $venda?->cliente;
                    $nomeCliente = $cliente->{'Nome conhecido'} ?? $venda?->{'Cliente'} ?? '';
                    $documentoCliente = $cliente->Cnpj ?? $cliente->Cpf ?? $venda?->Cnpj ?? $venda?->Cpf ?? '';
                    $intermediador = $venda?->{'Intermediador'} ?? '';

                    $linha = [];
                    foreach ($colunas as $coluna) {
                        if ($coluna === 'Nome Cliente') {
                            $linha[] = $nomeCliente;

                            continue;
                        }
                        if ($coluna === 'Documento Cliente') {
                            $linha[] = $documentoCliente;

                            continue;
                        }
                        if ($coluna === 'Intermediador') {
                            $linha[] = $intermediador;

                            continue;
                        }

                        $valor = $reg->{$coluna};

                        // Formatar datas
                        if (in_array($coluna, ['Data pagto', 'Data vencto', 'Data emissao']) && $valor) {
                            try {
                                $linha[] = date('d/m/Y', strtotime($valor));
                            } catch (\Exception $e) {
                                $linha[] = $valor;
                            }
                        }
                        // Formatar valores numéricos
                        elseif (in_array($coluna, ['Valor', 'Valor pago']) && is_numeric($valor)) {
                            $linha[] = number_format(abs($valor), 2, ',', '.');
                        }
                        // Outros campos
                        else {
                            $linha[] = $valor ?? '';
                        }
                    }
                    fputcsv($file, $linha);
                }

                fputcsv($file, []);
            }

            // ORDENS
            if ($registrosOrdens->count() > 0) {
                fputcsv($file, ['=== ORDENS DE SERVIÇO ===']);
                fputcsv($file, []);

                $primeiroRegistro = $registrosOrdens->first();
                $attrs = array_keys($primeiroRegistro->getAttributes());
                $primeiraColuna = array_shift($attrs);
                $colunas = array_merge([$primeiraColuna, 'Nome Cliente', 'Documento Cliente'], $attrs);
                fputcsv($file, $colunas);

                foreach ($registrosOrdens as $reg) {
                    $ordem = $reg->ordem;
                    $cliente = $ordem?->clienteByCodigo;
                    $nomeCliente = $cliente->{'Nome conhecido'} ?? $ordem?->{'Cliente'} ?? '';
                    $documentoCliente = $cliente->Cnpj ?? $cliente->Cpf ?? '';

                    $linha = [];
                    foreach ($colunas as $coluna) {
                        if ($coluna === 'Nome Cliente') {
                            $linha[] = $nomeCliente;

                            continue;
                        }
                        if ($coluna === 'Documento Cliente') {
                            $linha[] = $documentoCliente;

                            continue;
                        }

                        $valor = $reg->{$coluna};

                        // Formatar datas
                        if (in_array($coluna, ['Data pgto', 'Data vencto', 'Data emissao']) && $valor) {
                            try {
                                $linha[] = date('d/m/Y', strtotime($valor));
                            } catch (\Exception $e) {
                                $linha[] = $valor;
                            }
                        }
                        // Formatar valores numéricos
                        elseif (in_array($coluna, ['Valor', 'Valor pago']) && is_numeric($valor)) {
                            $linha[] = number_format(abs($valor), 2, ',', '.');
                        }
                        // Outros campos
                        else {
                            $linha[] = $valor ?? '';
                        }
                    }
                    fputcsv($file, $linha);
                }
            }

            if ($registrosVendas->count() == 0 && $registrosOrdens->count() == 0) {
                fputcsv($file, ['Nenhum registro encontrado para os filtros selecionados']);
            }

            fclose($file);
        };

        return $this->downloadExcelFromCsvCallback($callback, $filename);
    }

    public function retorno(Request $request, SantanderBankSettlementProvider $santanderProvider, BoletoBaixaService $boletoBaixaService)
    {
        $abaRetornoAtiva = $this->normalizarAbaRetorno(
            $request->query('aba_retorno', session('aba_retorno_ativa', 'api'))
        );
        $tipoRetornoManualSelecionado = $this->normalizarTipoRetornoManual(
            $request->query('tipo_retorno_manual', session('tipo_retorno_manual', 'cnab'))
        );

        $request->session()->put('aba_retorno_ativa', $abaRetornoAtiva);
        $request->session()->put('tipo_retorno_manual', $tipoRetornoManualSelecionado);

        $resultado = session('resultado_retorno');
        if ($request->boolean('descartar_retorno')) {
            $request->session()->forget('resultado_retorno');
            $resultado = null;
        } elseif ($this->deveLimparResultadoPorAba($resultado, $abaRetornoAtiva)) {
            $request->session()->forget('resultado_retorno');
            $resultado = null;
        } elseif ($this->deveLimparResultadoManual($resultado, $tipoRetornoManualSelecionado)) {
            $request->session()->forget('resultado_retorno');
            $resultado = null;
        }

        $resultadoBoletoTeste = session('resultado_boleto_teste');
        $resultadoWorkspaceTeste = session('resultado_workspace_teste');
        $resultadoConsultaBoletoTeste = session('resultado_consulta_boleto_teste');
        $qrCodePixSvgGerado = $this->gerarQrCodeSvg(data_get($resultadoBoletoTeste, 'payload.qrCodePix'));
        $filtrosRetorno = $this->normalizarFiltrosRetorno($request);
        $filtrosHistoricoRetorno = $this->normalizarFiltrosHistoricoRetorno($request);
        $parametrosExecucaoApi = $this->normalizarParametrosExecucaoApi(
            (array) $request->session()->get('parametros_execucao_api', [])
        );
        $historicoPerPage = in_array((int) $request->query('perPage', 100), [10, 15, 25, 50, 100], true)
            ? (int) $request->query('perPage', 100)
            : 100;
        [$sortByRetorno, $sortDirRetorno] = $this->normalizarOrdenacaoRetorno($request);
        $opcoesStatus = [];
        $opcoesMovimento = [];

        if (is_array($resultado) && isset($resultado['itens']) && is_array($resultado['itens'])) {
            $itensOriginais = array_map(function ($item) {
                return $this->normalizarItemRetornoParaVisualizacao((array) $item);
            }, $resultado['itens']);
            $itensFiltrados = array_values(array_filter($itensOriginais, function ($item) use ($filtrosRetorno) {
                return $this->itemRetornoPassaFiltros((array) $item, $filtrosRetorno);
            }));

            foreach ($itensOriginais as $item) {
                $status = trim((string) ($item['status'] ?? ''));
                if ($status !== '') {
                    $opcoesStatus[$status] = strtoupper(str_replace('_', ' ', $status));
                }

                $codigoMovimento = trim((string) ($item['codigo_movimento'] ?? ''));
                if ($codigoMovimento !== '') {
                    $descricao = trim((string) ($item['descricao_movimento'] ?? ''));
                    $opcoesMovimento[$codigoMovimento] = $descricao !== ''
                        ? "{$codigoMovimento} - {$descricao}"
                        : $codigoMovimento;
                }
            }

            ksort($opcoesStatus);
            ksort($opcoesMovimento);

            $itensFiltrados = $this->ordenarItensRetorno($itensFiltrados, $sortByRetorno, $sortDirRetorno);
            $resultado['itens'] = $itensFiltrados;
            $resultado['resumo']['total_ocorrencias_antes_filtro'] = count($itensOriginais);
            $resultado['resumo']['total_ocorrencias'] = count($itensFiltrados);
            $resultado['resumo']['ocorrencias_exibidas'] = count($itensFiltrados);
            $resultado['resumo']['filtros_aplicados'] = $this->filtrosRetornoAtivos($filtrosRetorno);
        }

        $historicoRetorno = collect();
        $opcoesStatusHistoricoRetorno = [];
        $ultimoProcessamentoApi = null;

        if (
            Schema::connection('tecnoar_api')->hasTable('retorno_processamento_items')
            && Schema::connection('tecnoar_api')->hasTable('retorno_processamentos')
        ) {
            $historicoQuery = RetornoProcessamentoItem::query()
                ->with('run')
                ->whereIn('source', ['WEBHOOK', 'POLLING', 'API']);

            $allowedSortColumns = [
                'processed_at' => 'processed_at',
                'cedente' => 'provider',
                'status' => 'status',
                'origem' => 'origin_type',
                'cliente' => 'client_name',
                'boleto' => 'boleto_number',
                'valor' => 'amount_paid',
                'data_credito' => 'credit_date',
            ];

            $histSortBy = $filtrosHistoricoRetorno['sort_by'] ?? '';
            $histSortDir = $filtrosHistoricoRetorno['sort_dir'] ?? 'desc';

            if (isset($allowedSortColumns[$histSortBy])) {
                $historicoQuery->orderBy($allowedSortColumns[$histSortBy], $histSortDir);
            } else {
                $historicoQuery->orderByDesc('processed_at');
            }

            $historicoQuery->orderByDesc('id');

            if (($filtrosHistoricoRetorno['status'] ?? '') !== '') {
                $historicoQuery->where('status', $filtrosHistoricoRetorno['status']);
            }

            if (($filtrosHistoricoRetorno['origem'] ?? '') !== '') {
                $historicoQuery->where('origin_type', $filtrosHistoricoRetorno['origem']);
            }

            if (($filtrosHistoricoRetorno['cliente'] ?? '') !== '') {
                $historicoQuery->where('client_name', 'like', '%'.$filtrosHistoricoRetorno['cliente'].'%');
            }

            if (($filtrosHistoricoRetorno['boleto'] ?? '') !== '') {
                $historicoQuery->where('boleto_number', 'like', '%'.$filtrosHistoricoRetorno['boleto'].'%');
            }

            if (($filtrosHistoricoRetorno['processado_em'] ?? '') !== '') {
                $historicoQuery->whereDate('processed_at', $filtrosHistoricoRetorno['processado_em']);
            }

            if (($filtrosHistoricoRetorno['valor_de'] ?? null) !== null) {
                $historicoQuery->where('amount_paid', '>=', round((float) $filtrosHistoricoRetorno['valor_de'], 2));
            }

            if (($filtrosHistoricoRetorno['valor_ate'] ?? null) !== null) {
                $historicoQuery->where('amount_paid', '<=', round((float) $filtrosHistoricoRetorno['valor_ate'], 2));
            }

            if (($filtrosHistoricoRetorno['data_credito'] ?? '') !== '') {
                $historicoQuery->whereDate('credit_date', $filtrosHistoricoRetorno['data_credito']);
            }

            if (($filtrosHistoricoRetorno['provider'] ?? '') !== '') {
                $this->aplicarFiltroProviderHistorico($historicoQuery, $filtrosHistoricoRetorno['provider']);
            }

            $historicoRetorno = $historicoQuery->paginate($historicoPerPage, ['*'], 'hist_page');
            $historicoRetorno->setCollection(
                $this->enriquecerHistoricoApi($historicoRetorno->getCollection(), $boletoBaixaService)
            );

            if ($request->query('partial') === 'api_history') {
                return response()->json([
                    'html' => view('financeiro.partials.retorno_api_table_body', compact('historicoRetorno'))->render(),
                    'total' => method_exists($historicoRetorno, 'total') ? $historicoRetorno->total() : $historicoRetorno->count(),
                ]);
            }

            $opcoesStatusHistoricoRetorno = RetornoProcessamentoItem::query()
                ->whereIn('source', ['WEBHOOK', 'POLLING', 'API'])
                ->whereNotNull('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status')
                ->all();
            $ultimoProcessamentoApi = RetornoProcessamento::query()
                ->whereIn('source', ['WEBHOOK', 'POLLING', 'API'])
                ->orderByDesc('processed_at')
                ->orderByDesc('id')
                ->first();
        }

        return view('financeiro.retorno', [
            'resultado' => $resultado,
            'resultadoBoletoTeste' => $resultadoBoletoTeste,
            'resultadoWorkspaceTeste' => $resultadoWorkspaceTeste,
            'resultadoConsultaBoletoTeste' => $resultadoConsultaBoletoTeste,
            'qrCodePixSvgGerado' => $qrCodePixSvgGerado,
            'filtrosRetorno' => $filtrosRetorno,
            'opcoesStatusRetorno' => $opcoesStatus,
            'opcoesMovimentoRetorno' => $opcoesMovimento,
            'historicoRetorno' => $historicoRetorno,
            'filtrosHistoricoRetorno' => $filtrosHistoricoRetorno,
            'opcoesStatusHistoricoRetorno' => $opcoesStatusHistoricoRetorno,
            'ultimoProcessamentoApi' => $ultimoProcessamentoApi,
            'abaRetornoAtiva' => $abaRetornoAtiva,
            'tipoRetornoManualSelecionado' => $tipoRetornoManualSelecionado,
            'parametrosExecucaoApi' => $parametrosExecucaoApi,
            'configuracaoBoletoTeste' => $santanderProvider->workspaceContext(),
            'ultimoBoletoTeste' => $santanderProvider->latestTestBillContext(),
        ]);
    }

    private function normalizarFiltrosRetorno(Request $request): array
    {
        $status = trim((string) $request->query('status', ''));
        $origem = trim((string) $request->query('origem', 'todos'));
        $movimento = preg_replace('/\D/', '', (string) $request->query('movimento', ''));

        return [
            'origem' => in_array($origem, ['todos', 'VENDA', 'OS', 'sem_origem'], true) ? $origem : 'todos',
            'status' => $status,
            'movimento' => $movimento,
            'cliente' => trim((string) $request->query('cliente', '')),
            'boleto' => preg_replace('/\D/', '', (string) $request->query('boleto', '')),
            'documento' => preg_replace('/\D/', '', (string) $request->query('documento', '')),
            'valor_de' => $this->normalizarValorRetornoFiltro($request->query('valor_de')),
            'valor_ate' => $this->normalizarValorRetornoFiltro($request->query('valor_ate')),
            'data_credito' => trim((string) $request->query('data_credito', '')),
        ];
    }

    private function normalizarOrdenacaoRetorno(Request $request): array
    {
        $sortBy = trim((string) $request->query('sort_by', ''));
        $sortDir = strtolower(trim((string) $request->query('sort_dir', 'asc'))) === 'desc' ? 'desc' : 'asc';

        $colunasPermitidas = ['movimento', 'valor_pago', 'data_credito', 'origem', 'cliente', 'boleto', 'documento', 'status'];
        if (! in_array($sortBy, $colunasPermitidas, true)) {
            $sortBy = '';
        }

        return [$sortBy, $sortDir];
    }

    private function filtrosRetornoAtivos(array $filtros): bool
    {
        return ($filtros['origem'] ?? 'todos') !== 'todos'
            || ($filtros['status'] ?? '') !== ''
            || ($filtros['movimento'] ?? '') !== ''
            || ($filtros['cliente'] ?? '') !== ''
            || ($filtros['boleto'] ?? '') !== ''
            || ($filtros['documento'] ?? '') !== ''
            || ($filtros['valor_de'] ?? null) !== null
            || ($filtros['valor_ate'] ?? null) !== null
            || ($filtros['data_credito'] ?? '') !== '';
    }

    private function normalizarFiltrosHistoricoRetorno(Request $request): array
    {
        $provider = trim((string) $request->query('hist_provider', ''));
        if (! in_array($provider, ['bradesco', 'santander', 'santander_filial', 'mercado_livre'], true)) {
            $provider = '';
        }

        return [
            'status' => trim((string) $request->query('hist_status', '')),
            'origem' => in_array($request->query('hist_origem', ''), ['OS', 'VENDA'], true)
                ? (string) $request->query('hist_origem')
                : '',
            'cliente' => trim((string) $request->query('hist_cliente', '')),
            'boleto' => trim((string) $request->query('hist_boleto', '')),
            'processado_em' => trim((string) $request->query('hist_processado_em', '')),
            'valor_de' => $this->normalizarValorRetornoFiltro($request->query('hist_valor_de')),
            'valor_ate' => $this->normalizarValorRetornoFiltro($request->query('hist_valor_ate')),
            'data_credito' => trim((string) $request->query('hist_data_credito', '')),
            'provider' => $provider,
            'sort_by' => trim((string) $request->query('hist_sort_by', '')),
            'sort_dir' => in_array(strtolower((string) $request->query('hist_sort_dir', '')), ['asc', 'desc'], true)
                ? strtolower((string) $request->query('hist_sort_dir'))
                : 'desc',
        ];
    }

    private function aplicarFiltroProviderHistorico($query, string $provider): void
    {
        if ($provider === 'santander_filial') {
            $query->where(function ($query) {
                $query->where('provider', 'santander_filial')
                    ->orWhere(function ($query) {
                        $query->where('provider', 'santander')
                            ->where(function ($query) {
                                foreach ($this->padroesPayloadSantanderFilial() as $padrao) {
                                    $query->orWhere('payload', 'like', $padrao);
                                }
                            });
                    });
            });

            return;
        }

        if ($provider === 'santander') {
            $query->where('provider', 'santander')
                ->where(function ($query) {
                    $query->whereNull('payload')
                        ->orWhere(function ($query) {
                            foreach ($this->padroesPayloadSantanderFilial() as $padrao) {
                                $query->where('payload', 'not like', $padrao);
                            }
                        });
                });

            return;
        }

        $query->where('provider', $provider);
    }

    private function padroesPayloadSantanderFilial(): array
    {
        return [
            '%"_webhook_context":"filial"%',
            '%"_webhook_context": "filial"%',
            '%"webhook_context":"filial"%',
            '%"webhook_context": "filial"%',
        ];
    }

    private function enriquecerHistoricoApi($historicoRetorno, BoletoBaixaService $boletoBaixaService)
    {
        if ($historicoRetorno->isEmpty()) {
            return $historicoRetorno;
        }

        $registrosFinanceiro = $boletoBaixaService->listarTitulosComBoleto(null, false);
        $registrosPorBoletoExato = [];
        $registrosPorIdentificadorExato = [];
        $registrosPorBoleto = [];
        $registrosPorIdentificador = [];
        $registrosPorOrigem = [];

        foreach ($registrosFinanceiro as $registro) {
            if (! is_array($registro)) {
                continue;
            }

            if (filled($registro['boleto_numero'] ?? null)) {
                foreach ($this->gerarVariantesIdentificadorHistorico((string) $registro['boleto_numero'], false) as $variante) {
                    $registrosPorBoletoExato[$variante][] = $registro;
                }

                foreach ($this->gerarVariantesIdentificadorHistorico((string) $registro['boleto_numero']) as $variante) {
                    $registrosPorBoleto[$variante][] = $registro;
                }
            }

            if (filled($registro['boleto_nosso_numero'] ?? null)) {
                foreach ($this->gerarVariantesIdentificadorHistorico((string) $registro['boleto_nosso_numero'], false) as $variante) {
                    $registrosPorIdentificadorExato[$variante][] = $registro;
                }

                foreach ($this->gerarVariantesIdentificadorHistorico((string) $registro['boleto_nosso_numero']) as $variante) {
                    $registrosPorIdentificador[$variante][] = $registro;
                }
            }

            if (filled($registro['numero_origem'] ?? null) && filled($registro['parcela'] ?? null)) {
                $registrosPorOrigem[($registro['tipo'] ?? '').'|'.$registro['numero_origem'].'|'.$registro['parcela']][] = $registro;
            }
        }

        $doadoresPorBoleto = [];
        $doadoresPorIdentificador = [];
        $doadoresPorOrigem = [];

        foreach ($historicoRetorno as $item) {
            $temOrigem = filled($item->origin_type) && filled($item->origin_number);
            $temCliente = filled($item->client_name);

            if (! $temOrigem && ! $temCliente) {
                continue;
            }

            if (filled($item->boleto_number)) {
                foreach ($this->gerarVariantesIdentificadorHistorico((string) $item->boleto_number, false) as $variante) {
                    $doadoresPorBoleto[$variante][] = $item;
                }
            }

            if (filled($item->identifier)) {
                foreach ($this->gerarVariantesIdentificadorHistorico((string) $item->identifier, false) as $variante) {
                    $doadoresPorIdentificador[$variante][] = $item;
                }
            }

            if (filled($item->origin_number) && filled($item->parcel)) {
                $chaveOrigem = (string) ($item->origin_type ?? '').'|'.$item->origin_number.'|'.$item->parcel;
                $doadoresPorOrigem[$chaveOrigem] ??= $item;
            }
        }

        foreach ($historicoRetorno as $item) {
            $item->display_provider = $this->resolverProviderHistoricoVisual($item);
            $registroResolvido = null;

            if (filled($item->origin_number) && filled($item->parcel)) {
                $chaveOrigem = (string) ($item->origin_type ?? '').'|'.$item->origin_number.'|'.$item->parcel;
                $candidatos = $registrosPorOrigem[$chaveOrigem] ?? [];
                $registroResolvido = $this->resolverRegistroHistoricoComContexto($item, $candidatos);
            }

            if ($registroResolvido === null && filled($item->boleto_number)) {
                foreach ($this->gerarVariantesIdentificadorHistorico((string) $item->boleto_number, false) as $variante) {
                    $candidatos = $registrosPorBoletoExato[$variante] ?? [];
                    $registroResolvido = $this->resolverRegistroHistoricoComContexto($item, $candidatos);
                    if ($registroResolvido !== null) {
                        break;
                    }
                }
            }

            if ($registroResolvido === null && filled($item->boleto_number)) {
                foreach ($this->gerarVariantesIdentificadorHistorico((string) $item->boleto_number) as $variante) {
                    $candidatos = $registrosPorBoleto[$variante] ?? [];
                    $registroResolvido = $this->resolverRegistroHistoricoComContexto($item, $candidatos);
                    if ($registroResolvido !== null) {
                        break;
                    }
                }
            }

            if ($registroResolvido === null && filled($item->identifier)) {
                foreach ($this->gerarVariantesIdentificadorHistorico((string) $item->identifier, false) as $variante) {
                    $candidatos = $registrosPorIdentificadorExato[$variante] ?? [];
                    $registroResolvido = $this->resolverRegistroHistoricoComContexto($item, $candidatos);
                    if ($registroResolvido !== null) {
                        break;
                    }
                }
            }

            if ($registroResolvido === null && filled($item->identifier)) {
                foreach ($this->gerarVariantesIdentificadorHistorico((string) $item->identifier) as $variante) {
                    $candidatos = $registrosPorIdentificador[$variante] ?? [];
                    $registroResolvido = $this->resolverRegistroHistoricoComContexto($item, $candidatos);
                    if ($registroResolvido !== null) {
                        break;
                    }
                }
            }

            if ($registroResolvido !== null) {
                $item->origin_type = $registroResolvido['tipo'] ?? $item->origin_type;
                $item->origin_number = $registroResolvido['numero_origem'] ?? $item->origin_number;
                $item->parcel = $registroResolvido['parcela'] ?? $item->parcel;
                $item->client_name = $registroResolvido['cliente_nome'] ?? $item->client_name;
                $item->boleto_number = $registroResolvido['boleto_numero'] ?? $item->boleto_number;
            }

            if (! filled($item->client_name)) {
                $candidatosCliente = [];

                if (filled($item->boleto_number)) {
                    foreach ($this->gerarVariantesIdentificadorHistorico((string) $item->boleto_number, false) as $variante) {
                        $candidatosCliente = array_merge($candidatosCliente, $registrosPorBoletoExato[$variante] ?? []);
                    }

                    foreach ($this->gerarVariantesIdentificadorHistorico((string) $item->boleto_number) as $variante) {
                        $candidatosCliente = array_merge($candidatosCliente, $registrosPorBoleto[$variante] ?? []);
                    }
                }

                if (filled($item->identifier)) {
                    foreach ($this->gerarVariantesIdentificadorHistorico((string) $item->identifier, false) as $variante) {
                        $candidatosCliente = array_merge($candidatosCliente, $registrosPorIdentificadorExato[$variante] ?? []);
                    }

                    foreach ($this->gerarVariantesIdentificadorHistorico((string) $item->identifier) as $variante) {
                        $candidatosCliente = array_merge($candidatosCliente, $registrosPorIdentificador[$variante] ?? []);
                    }
                }

                $nomeClienteResolvido = $this->resolverNomeClienteHistoricoComContexto($item, $candidatosCliente);
                if ($nomeClienteResolvido !== null) {
                    $item->client_name = $nomeClienteResolvido;
                }
            }

            $doadores = [];

            if (filled($item->origin_number) && filled($item->parcel)) {
                $chaveOrigem = (string) ($item->origin_type ?? '').'|'.$item->origin_number.'|'.$item->parcel;
                if (isset($doadoresPorOrigem[$chaveOrigem])) {
                    $doadores[] = $doadoresPorOrigem[$chaveOrigem];
                }
            }

            if (filled($item->boleto_number)) {
                foreach ($this->gerarVariantesIdentificadorHistorico((string) $item->boleto_number, false) as $variante) {
                    foreach ($doadoresPorBoleto[$variante] ?? [] as $doador) {
                        $doadores[] = $doador;
                    }
                }
            }

            if (filled($item->identifier)) {
                foreach ($this->gerarVariantesIdentificadorHistorico((string) $item->identifier, false) as $variante) {
                    foreach ($doadoresPorIdentificador[$variante] ?? [] as $doador) {
                        $doadores[] = $doador;
                    }
                }
            }

            $doadorResolvido = $this->resolverDoadorHistoricoComContexto($item, $doadores);
            if ($doadorResolvido !== null) {
                $doadores = [$doadorResolvido];
            } else {
                $doadores = [];
            }

            foreach ($doadores as $doador) {
                if (! filled($item->origin_type) && filled($doador->origin_type)) {
                    $item->origin_type = $doador->origin_type;
                }

                if (! filled($item->origin_number) && filled($doador->origin_number)) {
                    $item->origin_number = $doador->origin_number;
                }

                if (! filled($item->parcel) && filled($doador->parcel)) {
                    $item->parcel = $doador->parcel;
                }

                if (! filled($item->client_name) && filled($doador->client_name)) {
                    $item->client_name = $doador->client_name;
                }
            }

            if (! filled($item->client_name) && filled($item->origin_number) && filled($item->parcel)) {
                $chaveOrigem = (string) ($item->origin_type ?? '').'|'.$item->origin_number.'|'.$item->parcel;
                $candidatosPorOrigem = $registrosPorOrigem[$chaveOrigem] ?? [];
                $nomeClientePorOrigem = $this->resolverNomeClienteHistoricoComContexto($item, $candidatosPorOrigem);

                if ($nomeClientePorOrigem !== null) {
                    $item->client_name = $nomeClientePorOrigem;
                }
            }

            if (! filled($item->client_name) && filled($item->origin_type) && filled($item->origin_number) && filled($item->parcel)) {
                $nomeClienteDireto = $this->buscarNomeClienteNaOrigem(
                    (string) $item->origin_type,
                    (string) $item->origin_number,
                    (string) $item->parcel
                );

                if ($nomeClienteDireto !== null) {
                    $item->client_name = $nomeClienteDireto;
                }
            }
        }

        return $historicoRetorno;
    }

    private function resolverProviderHistoricoVisual(object $item): ?string
    {
        $provider = trim((string) ($item->provider ?? $item->run->provider ?? ''));

        if ($provider === 'santander_filial') {
            return 'santander_filial';
        }

        if ($provider === 'santander' && $this->payloadHistoricoIndicaSantanderFilial($item->payload ?? null)) {
            return 'santander_filial';
        }

        return $provider !== '' ? $provider : null;
    }

    private function payloadHistoricoIndicaSantanderFilial(mixed $payload): bool
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : $payload;
        }

        if (is_array($payload)) {
            $contexto = strtolower(trim((string) (
                data_get($payload, '_webhook_context')
                ?? data_get($payload, 'webhook_context')
                ?? data_get($payload, 'webhook._webhook_context')
                ?? data_get($payload, 'webhook.webhook_context')
                ?? ''
            )));

            return $contexto === 'filial';
        }

        return str_contains(strtolower((string) $payload), '"_webhook_context":"filial"')
            || str_contains(strtolower((string) $payload), '"webhook_context":"filial"');
    }

    private function resolverDoadorHistoricoComContexto(object $item, array $doadores): ?object
    {
        $candidatos = [];

        foreach ($doadores as $doador) {
            if (! is_object($doador)) {
                continue;
            }

            $chave = implode('|', [
                (string) ($doador->origin_type ?? ''),
                (string) ($doador->origin_number ?? ''),
                (string) ($doador->parcel ?? ''),
                (string) ($doador->boleto_number ?? ''),
                (string) ($doador->identifier ?? ''),
            ]);

            $candidatos[$chave] = $doador;
        }

        if (count($candidatos) === 1) {
            return array_values($candidatos)[0];
        }

        $documentoItem = $this->normalizarDocumentoHistorico((string) ($item->document ?? ''));
        $valorItem = $this->normalizarValorHistorico($item->amount_paid ?? null);
        $melhor = null;
        $melhorPontuacao = 0;
        $empatado = false;

        foreach ($candidatos as $candidato) {
            $pontuacao = 0;

            if ($documentoItem !== '' && $documentoItem === $this->normalizarDocumentoHistorico((string) ($candidato->document ?? ''))) {
                $pontuacao += 100;
            }

            $valorCandidato = $this->normalizarValorHistorico($candidato->amount_paid ?? null);
            if ($valorItem !== null && $valorCandidato !== null && abs($valorCandidato - $valorItem) < 0.01) {
                $pontuacao += 10;
            }

            if ($pontuacao === 0) {
                continue;
            }

            if ($pontuacao > $melhorPontuacao) {
                $melhor = $candidato;
                $melhorPontuacao = $pontuacao;
                $empatado = false;

                continue;
            }

            if ($pontuacao === $melhorPontuacao) {
                $empatado = true;
            }
        }

        if ($melhorPontuacao > 0 && ! $empatado) {
            return $melhor;
        }

        return null;
    }

    private function buscarNomeClienteNaOrigem(string $tipo, string $numeroOrigem, string $parcela): ?string
    {
        $tipo = strtoupper(trim($tipo));
        $numeroOrigem = trim($numeroOrigem);
        $parcela = trim($parcela);

        if ($tipo === '' || $numeroOrigem === '' || $parcela === '') {
            return null;
        }

        if ($tipo === 'VENDA') {
            $codigoCliente = DB::table('Vendas pagtos')
                ->where('No venda', $numeroOrigem)
                ->where('Parcela pagto', $parcela)
                ->value('Codigo cliente');

            $nomeOrigem = DB::table('Vendas')
                ->where('No venda', $numeroOrigem)
                ->value('Cliente');

            if (! filled($codigoCliente)) {
                $codigoCliente = DB::table('Vendas')
                    ->where('No venda', $numeroOrigem)
                    ->value('Codigo cliente');
            }
        } elseif ($tipo === 'OS') {
            $codigoCliente = DB::table('Ordens cheques')
                ->where('Numero ordem', $numeroOrigem)
                ->where('Parcela', $parcela)
                ->value('Codigo cliente');

            $nomeOrigem = DB::table('Ordens')
                ->where('Numero ordem', $numeroOrigem)
                ->value('Cliente');

            if (! filled($codigoCliente)) {
                $codigoCliente = DB::table('Ordens')
                    ->where('Numero ordem', $numeroOrigem)
                    ->value('Codigo cliente');
            }
        } else {
            return null;
        }

        $nome = null;

        if (filled($codigoCliente)) {
            $nome = DB::table('Clientes')
                ->where('Codigo cliente', $codigoCliente)
                ->value('Nome conhecido');

            if (! filled($nome)) {
                $nome = DB::table('Clientes')
                    ->where('Codigo cliente', $codigoCliente)
                    ->value('Nome cliente');
            }
        }

        if (! filled($nome)) {
            $nome = $nomeOrigem ?? null;
        }

        $nome = trim((string) $nome);

        return $nome !== '' ? $nome : null;
    }

    private function resolverNomeClienteHistoricoComContexto(object $item, array $candidatos): ?string
    {
        $registrosUnicos = [];

        foreach ($candidatos as $candidato) {
            if (! is_array($candidato)) {
                continue;
            }

            $chave = implode('|', [
                $candidato['tipo'] ?? '',
                $candidato['numero_origem'] ?? '',
                $candidato['parcela'] ?? '',
                $candidato['boleto_numero'] ?? '',
            ]);

            $registrosUnicos[$chave] = $candidato;
        }

        if (empty($registrosUnicos)) {
            return null;
        }

        $nomes = array_values(array_unique(array_filter(array_map(
            fn (array $registro) => trim((string) ($registro['cliente_nome'] ?? '')),
            array_values($registrosUnicos)
        ))));

        if (count($nomes) === 1) {
            return $nomes[0];
        }

        $registroResolvido = $this->resolverRegistroHistoricoComContexto($item, array_values($registrosUnicos));
        if ($registroResolvido !== null) {
            $nome = trim((string) ($registroResolvido['cliente_nome'] ?? ''));

            return $nome !== '' ? $nome : null;
        }

        return null;
    }

    private function resolverRegistroUnicoHistorico(array $candidatos): ?array
    {
        if (empty($candidatos)) {
            return null;
        }

        $unicos = [];

        foreach ($candidatos as $candidato) {
            if (! is_array($candidato)) {
                continue;
            }

            $chave = implode('|', [
                $candidato['tipo'] ?? '',
                $candidato['numero_origem'] ?? '',
                $candidato['parcela'] ?? '',
                $candidato['boleto_numero'] ?? '',
            ]);

            $unicos[$chave] = $candidato;
        }

        return count($unicos) === 1 ? array_values($unicos)[0] : null;
    }

    private function resolverRegistroHistoricoComContexto(object $item, array $candidatos): ?array
    {
        $unico = $this->resolverRegistroUnicoHistorico($candidatos);
        if ($unico !== null) {
            return $unico;
        }

        $unicos = [];

        foreach ($candidatos as $candidato) {
            if (! is_array($candidato)) {
                continue;
            }

            $chave = implode('|', [
                $candidato['tipo'] ?? '',
                $candidato['numero_origem'] ?? '',
                $candidato['parcela'] ?? '',
                $candidato['boleto_numero'] ?? '',
            ]);

            $unicos[$chave] = $candidato;
        }

        if (count($unicos) <= 1) {
            return count($unicos) === 1 ? array_values($unicos)[0] : null;
        }

        $documentoItem = $this->normalizarDocumentoHistorico((string) ($item->document ?? ''));
        $valorItem = $this->normalizarValorHistorico($item->amount_paid ?? null);
        $melhor = null;
        $melhorPontuacao = 0;
        $empatado = false;

        foreach ($unicos as $candidato) {
            $pontuacao = 0;

            if ($this->historicoIndicaBaixaConcluida($item)) {
                $valorPagoCandidato = $this->normalizarValorHistorico($candidato['valor_pago'] ?? null);
                if (
                    ! empty($candidato['data_pagto'])
                    && $valorItem !== null
                    && $valorPagoCandidato !== null
                    && abs($valorPagoCandidato - $valorItem) < 0.01
                ) {
                    $pontuacao += 1000;
                }
            }

            $documentosCandidato = array_filter(array_unique(array_map(
                fn ($valor) => $this->normalizarDocumentoHistorico((string) $valor),
                array_merge(
                    [$candidato['cliente_documento'] ?? null, $candidato['documento_numero'] ?? null],
                    is_array($candidato['cliente_documentos'] ?? null) ? $candidato['cliente_documentos'] : []
                )
            )));

            if ($documentoItem !== '' && in_array($documentoItem, $documentosCandidato, true)) {
                $pontuacao += 100;
            }

            $valorTitulo = $this->normalizarValorHistorico($candidato['valor_titulo'] ?? null);
            $valorPago = $this->normalizarValorHistorico($candidato['valor_pago'] ?? null);
            if ($valorItem !== null) {
                if ($valorTitulo !== null && abs($valorTitulo - $valorItem) < 0.01) {
                    $pontuacao += 10;
                }

                if ($valorPago !== null && abs($valorPago - $valorItem) < 0.01) {
                    $pontuacao += 5;
                }
            }

            if ($pontuacao === 0) {
                continue;
            }

            if ($pontuacao > $melhorPontuacao) {
                $melhor = $candidato;
                $melhorPontuacao = $pontuacao;
                $empatado = false;

                continue;
            }

            if ($pontuacao === $melhorPontuacao) {
                $empatado = true;
            }
        }

        if ($melhorPontuacao > 0 && ! $empatado) {
            return $melhor;
        }

        return null;
    }

    private function historicoIndicaBaixaConcluida(object $item): bool
    {
        return in_array(strtolower(trim((string) ($item->status ?? ''))), ['baixado', 'ja_baixado'], true);
    }

    private function normalizarDocumentoHistorico(string $documento): string
    {
        return preg_replace('/\D+/', '', trim($documento));
    }

    private function normalizarValorHistorico(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return round((float) $valor, 2);
    }

    private function gerarVariantesIdentificadorHistorico(string $valor, bool $permitirTruncado = true): array
    {
        $valor = trim($valor);
        if ($valor === '') {
            return [];
        }

        $variantes = [$valor => true];
        $digitos = preg_replace('/\D+/', '', $valor);

        if ($digitos !== '') {
            $variantes[$digitos] = true;

            $semZeros = ltrim($digitos, '0');
            if ($semZeros !== '') {
                $variantes[$semZeros] = true;
            }

            if ($permitirTruncado && strlen($digitos) > 1) {
                $semUltimo = substr($digitos, 0, -1);
                $variantes[$semUltimo] = true;

                $semZerosSemUltimo = ltrim($semUltimo, '0');
                if ($semZerosSemUltimo !== '') {
                    $variantes[$semZerosSemUltimo] = true;
                }
            }
        }

        return array_keys($variantes);
    }

    private function mapearFonteRetorno(string $tipoRetorno): string
    {
        return match ($tipoRetorno) {
            'cnab' => 'TXT',
            'shopee' => 'SHOPEE',
            'ml' => 'ML',
            'magalu' => 'MAGALU',
            default => strtoupper($tipoRetorno),
        };
    }

    private function ordenarItensRetorno(array $itens, string $sortBy, string $sortDir): array
    {
        if ($sortBy === '' || empty($itens)) {
            return array_values($itens);
        }

        $itensComIndice = array_map(
            fn (array $item, int $indice) => ['item' => $item, 'indice' => $indice],
            array_values($itens),
            array_keys(array_values($itens))
        );

        usort($itensComIndice, function (array $a, array $b) use ($sortBy, $sortDir) {
            $valorA = $this->obterValorOrdenacaoRetorno($a['item'], $sortBy);
            $valorB = $this->obterValorOrdenacaoRetorno($b['item'], $sortBy);

            $comparacao = $this->compararValoresOrdenacaoRetorno($valorA, $valorB);
            if ($comparacao === 0) {
                $comparacao = $a['indice'] <=> $b['indice'];
            }

            return $sortDir === 'desc' ? -$comparacao : $comparacao;
        });

        return array_map(fn (array $entrada) => $entrada['item'], $itensComIndice);
    }

    private function obterValorOrdenacaoRetorno(array $item, string $sortBy): mixed
    {
        return match ($sortBy) {
            'movimento' => trim((string) ($item['codigo_movimento'] ?? $item['id_pedido'] ?? '')),
            'valor_pago' => round((float) ($item['valor_pago'] ?? 0), 2),
            'data_credito' => $this->obterTimestampOrdenacaoRetorno($item),
            'origem' => $this->normalizarTextoRetornoBusca(trim(implode(' ', array_filter([
                (string) ($item['origem_tipo'] ?? ''),
                (string) ($item['origem_numero'] ?? ''),
                (string) ($item['parcela'] ?? ''),
            ])))),
            'cliente' => $this->normalizarTextoRetornoBusca((string) ($item['cliente_nome'] ?? '')),
            'boleto' => preg_replace('/\D/', '', (string) ($item['numero_boleto'] ?? '')),
            'documento' => preg_replace('/\D/', '', (string) ($item['documento_sacado'] ?? '')),
            'status' => $this->normalizarTextoRetornoBusca((string) ($item['status'] ?? '')),
            default => '',
        };
    }

    private function obterTimestampOrdenacaoRetorno(array $item): int
    {
        $data = $item['data_pagamento'] ?? $item['data_credito'] ?? null;
        if (blank($data)) {
            return 0;
        }

        try {
            return Carbon::parse((string) $data)->timestamp;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function compararValoresOrdenacaoRetorno(mixed $valorA, mixed $valorB): int
    {
        if (is_numeric($valorA) && is_numeric($valorB)) {
            return (float) $valorA <=> (float) $valorB;
        }

        return strnatcasecmp((string) $valorA, (string) $valorB);
    }

    private function itemRetornoPassaFiltros(array $item, array $filtros): bool
    {
        $origemFiltro = $filtros['origem'] ?? 'todos';
        $origemItem = trim((string) ($item['origem_tipo'] ?? ''));
        if ($origemFiltro === 'sem_origem' && $origemItem !== '') {
            return false;
        }
        if (in_array($origemFiltro, ['VENDA', 'OS'], true) && $origemItem !== $origemFiltro) {
            return false;
        }

        $statusFiltro = trim((string) ($filtros['status'] ?? ''));
        if ($statusFiltro !== '' && trim((string) ($item['status'] ?? '')) !== $statusFiltro) {
            return false;
        }

        $movimentoFiltro = trim((string) ($filtros['movimento'] ?? ''));
        if ($movimentoFiltro !== '' && trim((string) ($item['codigo_movimento'] ?? '')) !== $movimentoFiltro) {
            return false;
        }

        $clienteFiltro = $this->normalizarTextoRetornoBusca(trim((string) ($filtros['cliente'] ?? '')));
        if ($clienteFiltro !== '') {
            $clienteItem = $this->normalizarTextoRetornoBusca((string) ($item['cliente_nome'] ?? ''));
            if (! str_contains($clienteItem, $clienteFiltro)) {
                return false;
            }
        }

        $boletoFiltro = trim((string) ($filtros['boleto'] ?? ''));
        if ($boletoFiltro !== '') {
            $boletoItem = preg_replace('/\D/', '', (string) ($item['numero_boleto'] ?? ''));
            if ($boletoItem === '' || ! str_contains($boletoItem, $boletoFiltro)) {
                return false;
            }
        }

        $documentoFiltro = trim((string) ($filtros['documento'] ?? ''));
        if ($documentoFiltro !== '') {
            $documentoItem = preg_replace('/\D/', '', (string) ($item['documento_sacado'] ?? ''));
            if ($documentoItem === '' || ! str_contains($documentoItem, $documentoFiltro)) {
                return false;
            }
        }

        $valorDe = $filtros['valor_de'] ?? null;
        if ($valorDe !== null) {
            $valorItem = round((float) ($item['valor_pago'] ?? 0), 2);
            if ($valorItem + 0.009 < round((float) $valorDe, 2)) {
                return false;
            }
        }

        $valorAte = $filtros['valor_ate'] ?? null;
        if ($valorAte !== null) {
            $valorItem = round((float) ($item['valor_pago'] ?? 0), 2);
            if ($valorItem - 0.009 > round((float) $valorAte, 2)) {
                return false;
            }
        }

        $dataCreditoFiltro = trim((string) ($filtros['data_credito'] ?? ''));
        if ($dataCreditoFiltro !== '') {
            $dataPagtoItem = trim((string) ($item['data_pagamento'] ?? ''));
            $dataCreditoItem = trim((string) ($item['data_credito'] ?? ''));
            $dateToMatch = $dataPagtoItem ?: $dataCreditoItem;

            if ($dateToMatch === '' || ! str_starts_with($dateToMatch, $dataCreditoFiltro)) {
                return false;
            }
        }

        return true;
    }

    private function normalizarItemRetornoParaVisualizacao(array $item): array
    {
        if (
            empty($item['origem_tipo'])
            && ! empty($item['origem_numero'])
            && ! empty($item['id_pedido'])
        ) {
            $item['origem_tipo'] = 'VENDA';
        }

        return $item;
    }

    private function normalizarTextoRetornoBusca(string $texto): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($texto, 'UTF-8');
        }

        return strtolower($texto);
    }

    private function normalizarValorRetornoFiltro(mixed $valor): ?float
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        return round($this->parseMarketplaceAmount($texto), 2);
    }

    private function gerarQrCodeSvg(?string $payload): ?string
    {
        $payload = trim((string) $payload);
        if ($payload === '') {
            return null;
        }

        try {
            $writer = new Writer(
                new ImageRenderer(
                    new RendererStyle(220, 1),
                    new SvgImageBackEnd
                )
            );

            return $writer->writeString($payload);
        } catch (\Throwable $e) {
            Log::warning('Nao foi possivel gerar SVG do QR Code Pix', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function normalizarAbaRetorno(?string $aba): string
    {
        $aba = trim((string) $aba);

        return in_array($aba, ['api', 'manual'], true)
            ? $aba
            : 'api';
    }

    private function normalizarTipoRetornoManual(?string $tipo): string
    {
        $tipo = trim((string) $tipo);

        return in_array($tipo, ['cnab', 'shopee', 'ml', 'magalu'], true)
            ? $tipo
            : 'cnab';
    }

    private function normalizarParametrosExecucaoApi(array $dados): array
    {
        $provedor = trim((string) ($dados['provedor_api'] ?? 'todos'));

        if (! in_array($provedor, ['todos', 'santander', 'bradesco'], true)) {
            $provedor = 'todos';
        }

        $receiptsStartDate = trim((string) ($dados['receipts_start_date'] ?? ''));
        $receiptsEndDate = trim((string) ($dados['receipts_end_date'] ?? ''));
        $receiptCommitmentNumber = trim((string) ($dados['receipt_commitment_number'] ?? ''));
        $receiptDocument = preg_replace('/\D+/', '', (string) ($dados['receipt_document'] ?? ''));

        return [
            'provedor_api' => $provedor,
            'simular_api' => (bool) ($dados['simular_api'] ?? false),
            'receipts_start_date' => $receiptsStartDate !== '' ? $receiptsStartDate : null,
            'receipts_end_date' => $receiptsEndDate !== '' ? $receiptsEndDate : null,
            'receipt_commitment_number' => $receiptCommitmentNumber !== '' ? $receiptCommitmentNumber : null,
            'receipt_document' => $receiptDocument !== '' ? $receiptDocument : null,
        ];
    }

    private function deveLimparResultadoManual(mixed $resultado, string $tipoSelecionado): bool
    {
        $tipoResultado = $this->resolverTipoRetornoResultado($resultado);

        return $tipoResultado !== null && $tipoResultado !== $tipoSelecionado;
    }

    private function resolverTipoRetornoResultado(mixed $resultado): ?string
    {
        if (! is_array($resultado)) {
            return null;
        }

        $fonte = strtoupper((string) data_get($resultado, 'resumo.fonte_processamento', ''));
        $layout = strtoupper((string) data_get($resultado, 'resumo.layout', ''));

        if ($fonte === 'API' || $layout === 'API') {
            return null;
        }

        return match ($layout) {
            'SHOPEE' => 'shopee',
            'MERCADO LIVRE' => 'ml',
            'MAGALU' => 'magalu',
            default => 'cnab',
        };
    }

    private function deveLimparResultadoPorAba(mixed $resultado, string $abaAtiva): bool
    {
        $abaResultado = $this->resolverAbaResultado($resultado);

        return $abaResultado !== null && $abaResultado !== $abaAtiva;
    }

    private function resolverAbaResultado(mixed $resultado): ?string
    {
        if (! is_array($resultado)) {
            return null;
        }

        $fonte = strtoupper((string) data_get($resultado, 'resumo.fonte_processamento', ''));
        $layout = strtoupper((string) data_get($resultado, 'resumo.layout', ''));

        return ($fonte === 'API' || $layout === 'API')
            ? 'api'
            : 'manual';
    }

    public function processarRetorno(
        Request $request,
        RetornoCnabService $retornoCnabService,
        ShopeeReturnService $shopeeReturnService,
        MercadoLivreReturnService $mercadoLivreReturnService,
        MagaluReturnService $magaluReturnService,
        RetornoProcessamentoLoggerService $logger
    ) {
        $validated = $request->validate([
            'arquivo_retorno' => ['required', 'file', 'max:20480'],
            'data_baixa' => ['nullable', 'date'],
            'simular' => ['nullable', 'boolean'],
            'tipo_retorno' => ['required', 'string', 'in:cnab,shopee,ml,magalu'],
        ]);

        try {
            if ($validated['tipo_retorno'] === 'shopee') {
                $resultado = $shopeeReturnService->processar(
                    $validated['arquivo_retorno'],
                    $request->boolean('simular'),
                    $validated['data_baixa'] ?? null
                );
            } elseif ($validated['tipo_retorno'] === 'ml') {
                $resultado = $mercadoLivreReturnService->processar(
                    $validated['arquivo_retorno'],
                    $request->boolean('simular'),
                    $validated['data_baixa'] ?? null
                );
            } elseif ($validated['tipo_retorno'] === 'magalu') {
                $resultado = $magaluReturnService->processar(
                    $validated['arquivo_retorno'],
                    $request->boolean('simular'),
                    $validated['data_baixa'] ?? null
                );
            } else {
                $resultado = $retornoCnabService->processar(
                    $validated['arquivo_retorno'],
                    $request->boolean('simular'),
                    $validated['data_baixa'] ?? null
                );
            }

            $resultado['resumo']['fonte_processamento'] = $this->mapearFonteRetorno($validated['tipo_retorno']);
            $resultado['resumo']['gatilho'] = 'manual';
            $request->session()->put('resultado_retorno', $resultado);
            $request->session()->put('tipo_retorno_manual', $validated['tipo_retorno']);
            $logger->registrar($resultado, [
                'source' => $this->mapearFonteRetorno($validated['tipo_retorno']),
                'trigger_type' => 'manual',
            ]);

            return redirect()
                ->route('financeiro.retorno', [
                    'aba_retorno' => 'manual',
                    'tipo_retorno_manual' => $validated['tipo_retorno'],
                ])
                ->with('aba_retorno_ativa', 'manual');
        } catch (\Throwable $e) {
            Log::error('Erro ao processar arquivo de retorno financeiro', [
                'tipo' => $request->input('tipo_retorno'),
                'arquivo' => $request->file('arquivo_retorno')?->getClientOriginalName(),
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('financeiro.retorno', [
                    'aba_retorno' => 'manual',
                    'tipo_retorno_manual' => $request->input('tipo_retorno', 'cnab'),
                ])
                ->withInput()
                ->with('aba_retorno_ativa', 'manual')
                ->withErrors([
                    'arquivo_retorno' => 'Não foi possível processar o retorno. '.$e->getMessage(),
                ]);
        }
    }

    public function processarRetornoApi(
        Request $request,
        BankApiSettlementService $bankApiSettlementService,
        RetornoProcessamentoLoggerService $logger
    ) {
        $tipoRetornoManual = $this->normalizarTipoRetornoManual(
            $request->input('tipo_retorno_manual', session('tipo_retorno_manual', 'cnab'))
        );
        $request->session()->put('tipo_retorno_manual', $tipoRetornoManual);

        $validated = $request->validate([
            'provedor_api' => ['nullable', 'string', 'in:todos,santander,bradesco'],
            'simular_api' => ['nullable', 'boolean'],
            'receipts_start_date' => ['nullable', 'date'],
            'receipts_end_date' => ['nullable', 'date', 'after_or_equal:receipts_start_date'],
            'receipt_commitment_number' => ['nullable', 'string', 'max:120'],
            'receipt_document' => ['nullable', 'string', 'max:30'],
        ]);

        $provider = ($validated['provedor_api'] ?? 'todos') === 'todos'
            ? null
            : $validated['provedor_api'];
        $parametrosExecucaoApi = $this->normalizarParametrosExecucaoApi([
            'provedor_api' => $validated['provedor_api'] ?? 'todos',
            'simular_api' => $request->boolean('simular_api'),
            'receipts_start_date' => $validated['receipts_start_date'] ?? null,
            'receipts_end_date' => $validated['receipts_end_date'] ?? null,
            'receipt_commitment_number' => $validated['receipt_commitment_number'] ?? null,
            'receipt_document' => $validated['receipt_document'] ?? null,
        ]);
        $request->session()->put('parametros_execucao_api', $parametrosExecucaoApi);

        try {
            $resultado = $bankApiSettlementService->processar(
                $request->boolean('simular_api'),
                $provider,
                'manual',
                $parametrosExecucaoApi
            );

            $request->session()->put('resultado_retorno', $resultado);
            $logger->registrar($resultado, [
                'source' => 'API',
                'provider' => $provider,
                'trigger_type' => 'manual',
            ]);

            return redirect()
                ->route('financeiro.retorno', [
                    'aba_retorno' => 'api',
                    'tipo_retorno_manual' => $tipoRetornoManual,
                ])
                ->with('aba_retorno_ativa', 'api');
        } catch (\Throwable $e) {
            Log::error('Erro ao processar conciliação automática por API', [
                'provider' => $provider,
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('financeiro.retorno', [
                    'aba_retorno' => 'api',
                    'tipo_retorno_manual' => $tipoRetornoManual,
                ])
                ->withInput()
                ->with('aba_retorno_ativa', 'api')
                ->withErrors([
                    'provedor_api' => 'Não foi possível processar a conciliação automática. '.$e->getMessage(),
                ]);
        }
    }

    public function gerarBoletoTesteSantander(
        Request $request,
        SantanderBankSettlementProvider $santanderProvider
    ) {
        $tipoRetornoManual = $this->normalizarTipoRetornoManual(
            $request->input('tipo_retorno_manual', session('tipo_retorno_manual', 'cnab'))
        );
        $request->session()->put('tipo_retorno_manual', $tipoRetornoManual);

        $validated = $request->validate([
            'workspace_id' => ['nullable', 'string', 'max:120'],
            'nominal_value' => ['nullable', 'numeric', 'min:0.01'],
            'due_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $resultado = $santanderProvider->generateTestBankSlip([
            'workspace_id' => $validated['workspace_id'] ?? null,
            'nominal_value' => $validated['nominal_value'] ?? 10,
            'due_days' => $validated['due_days'] ?? 7,
        ]);

        return redirect()
            ->route('financeiro.retorno', [
                'aba_retorno' => 'boleto',
                'tipo_retorno_manual' => $tipoRetornoManual,
            ])
            ->withInput()
            ->with('aba_retorno_ativa', 'boleto')
            ->with('resultado_boleto_teste', $resultado);
    }

    public function criarWorkspaceTesteSantander(
        Request $request,
        SantanderBankSettlementProvider $santanderProvider
    ) {
        $tipoRetornoManual = $this->normalizarTipoRetornoManual(
            $request->input('tipo_retorno_manual', session('tipo_retorno_manual', 'cnab'))
        );
        $request->session()->put('tipo_retorno_manual', $tipoRetornoManual);

        $resultado = $santanderProvider->createAndStoreTestWorkspace();

        return redirect()
            ->route('financeiro.retorno', [
                'aba_retorno' => 'boleto',
                'tipo_retorno_manual' => $tipoRetornoManual,
            ])
            ->with('aba_retorno_ativa', 'boleto')
            ->with('resultado_workspace_teste', $resultado);
    }

    public function consultarBoletoTesteSantander(
        Request $request,
        SantanderBankSettlementProvider $santanderProvider
    ) {
        $tipoRetornoManual = $this->normalizarTipoRetornoManual(
            $request->input('tipo_retorno_manual', session('tipo_retorno_manual', 'cnab'))
        );
        $request->session()->put('tipo_retorno_manual', $tipoRetornoManual);

        $validated = $request->validate([
            'bank_number' => ['nullable', 'string', 'max:60'],
        ]);

        $resultado = $santanderProvider->consultTestBankSlip($validated['bank_number'] ?? null);

        return redirect()
            ->route('financeiro.retorno', [
                'aba_retorno' => 'boleto',
                'tipo_retorno_manual' => $tipoRetornoManual,
            ])
            ->with('aba_retorno_ativa', 'boleto')
            ->with('resultado_consulta_boleto_teste', $resultado);
    }

    /**
     * Display the fechamento-2 dashboard with lazy loading
     * Only loads "Visão Geral" data initially for faster page load
     */
    public function index2(Request $request)
    {
        $ano = $request->get('ano', date('Y'));
        $filial = $request->get('filial', 'todas');
        $mes = $this->normalizarMesFiltro($request->get('mes'));
        $intermediadores = $this->listarIntermediadores();

        // Only load Visão Geral data initially for faster page load
        $visaoGeral = $this->getVisaoGeralData($ano, $filial, $mes);

        $data = array_merge($visaoGeral, ['ano' => $ano, 'filial' => $filial, 'mes' => $mes, 'intermediadores' => $intermediadores]);

        return view('financeiro.fechamento.index', $data);
    }

    /**
     * AJAX endpoint for lazy loading tab data
     * Returns rendered HTML for the requested tab
     */
    public function loadTabData($tabName, Request $request)
    {
        $ano = $request->get('ano', date('Y'));
        $filial = $request->get('filial', 'todas');
        $mes = $this->normalizarMesFiltro($request->get('mes'));

        // Define mesesLabels for all partials
        $mesesLabels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        $result = match ($tabName) {
            'financeiro' => [
                'data' => $this->getFinanceiroData($ano, $filial, $mes),
                'partial' => 'financeiro.fechamento.partials.financeiro',
            ],
            'despesas' => [
                'data' => $this->getDespesasData($ano, $filial, $mes),
                'partial' => 'financeiro.fechamento.partials.despesas',
            ],
            'contabil' => [
                'data' => $this->getContabilData($ano, $filial, $mes),
                'partial' => 'financeiro.fechamento.partials.contabil',
            ],
            'comparativos' => [
                'data' => $this->getComparativosData($ano, $filial, $mes),
                'partial' => 'financeiro.fechamento.partials.comparativos',
            ],
            default => abort(404)
        };

        // Merge mesesLabels with the tab-specific data
        $viewData = array_merge($result['data'], ['mesesLabels' => $mesesLabels]);

        // Render the partial with data and return as HTML
        $html = view($result['partial'], $viewData)->render();

        return response()->json(['html' => $html]);
    }

    public function downloadMes(Request $request, string $mes)
    {
        $disk = Storage::disk('local');
        $tipoXml = strtolower((string) $request->query('tipo_xml', 'entrada'));
        $baseDir = match ($tipoXml) {
            'entrada' => '2026',
            'saida' => 'notas',
            default => abort(404, 'Tipo de XML inválido.'),
        };

        $numeroMes = (int) $mes;
        if ($numeroMes < 1 || $numeroMes > 12) {
            abort(404, 'Mês inválido.');
        }

        $meses = [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        ];

        if (! $disk->exists($baseDir)) {
            abort(404, "Diretório não encontrado: storage/app/private/{$baseDir}");
        }

        $folderEsperada = "{$numeroMes} - {$meses[$numeroMes]}";
        $targetDir = null;

        foreach ($disk->directories($baseDir) as $directory) {
            if (basename($directory) === $folderEsperada) {
                $targetDir = $directory;
                break;
            }
        }

        if (! $targetDir) {
            foreach ($disk->directories($baseDir) as $directory) {
                if (preg_match('/^0?'.$numeroMes.'\s*-\s*/', basename($directory)) === 1) {
                    $targetDir = $directory;
                    break;
                }
            }
        }

        if (! $targetDir || ! $disk->exists($targetDir)) {
            abort(404, "Pasta do mês não encontrada: {$folderEsperada}");
        }

        $xmlFiles = array_values(array_filter($disk->allFiles($targetDir), function ($path) {
            return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xml';
        }));

        if (count($xmlFiles) === 0) {
            abort(404, "Nenhum XML encontrado em {$folderEsperada}");
        }

        $slugMes = strtolower(str_replace('ç', 'c', $meses[$numeroMes]));
        $zipName = sprintf('%02d-%s-%s-xml-contabilidade.zip', $numeroMes, $slugMes, $tipoXml);
        $zipRelativePath = "tmp/{$zipName}";
        $disk->makeDirectory('tmp');

        $zipFullPath = $disk->path($zipRelativePath);
        $zip = new ZipArchive;
        if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Não foi possível criar o ZIP.');
        }

        $folderName = basename($targetDir);
        foreach ($xmlFiles as $relativePath) {
            $fullPath = $disk->path($relativePath);
            $relativeFromMonthDir = ltrim(str_replace($targetDir, '', $relativePath), '/');
            $nameInsideZip = $folderName.'/'.$relativeFromMonthDir;
            $zip->addFile($fullPath, $nameInsideZip);
        }

        $zip->close();

        return response()->download($zipFullPath, $zipName)->deleteFileAfterSend(true);
    }

    public function baixarParcelaMarketplace(Request $request)
    {
        $validated = $request->validate([
            'no_venda' => 'required',
            'parcela' => 'required',
            'valor_pago' => 'required|numeric',
            'data_pagto' => 'required|date',
        ]);

        try {
            $parcela = VendasPagto::where('No venda', $validated['no_venda'])
                ->where('Parcela pagto', $validated['parcela'])
                ->firstOrFail();

            if ($parcela->{'Data pagto'} && $parcela->{'Data pagto'} != '0000-00-00') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta parcela já possui uma data de pagamento ('.$parcela->{'Data pagto'}.').',
                ], 400);
            }

            $parcela->{'Data pagto'} = $validated['data_pagto'];
            $parcela->{'Valor pago'} = $validated['valor_pago'];
            $parcela->save();

            return response()->json([
                'success' => true,
                'message' => 'Baixa realizada com sucesso!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao realizar baixa: '.$e->getMessage(),
            ], 400);
        }
    }

    private function buscarClientesPorCodigo(array $codigos): array
    {
        $codigos = array_values(array_filter(array_unique($codigos), fn ($v) => ! is_null($v) && $v !== ''));
        if (empty($codigos)) {
            return [];
        }

        $clientes = [];

        foreach (array_chunk($codigos, self::SQLSERVER_SAFE_WHERE_IN_CHUNK) as $lote) {
            $resultadoLote = DB::table('Clientes')
                ->whereIn('Codigo cliente', $lote)
                ->select('Codigo cliente', 'Nome conhecido', 'Nome cliente', 'Cnpj', 'Cpf')
                ->get()
                ->mapWithKeys(function ($row) {
                    return [
                        (string) $row->{'Codigo cliente'} => [
                            'nome' => $row->{'Nome conhecido'} ?? $row->{'Nome cliente'} ?? '',
                            'documento' => $row->Cnpj ?? $row->Cpf ?? '',
                        ],
                    ];
                })
                ->toArray();

            $clientes = array_replace($clientes, $resultadoLote);
        }

        return $clientes;
    }

    private function buscarIntermediadoresPorNumeroVenda(array $numerosVenda): array
    {
        $numerosVenda = array_values(array_filter(array_unique($numerosVenda), fn ($v) => ! is_null($v) && $v !== ''));
        if (empty($numerosVenda)) {
            return [];
        }

        $intermediadores = [];

        foreach (array_chunk($numerosVenda, self::SQLSERVER_SAFE_WHERE_IN_CHUNK) as $lote) {
            $resultadoLote = DB::table('Vendas')
                ->whereIn('No venda', $lote)
                ->pluck('Intermediador', 'No venda')
                ->mapWithKeys(fn ($valor, $chave) => [(string) $chave => $valor])
                ->toArray();

            $intermediadores = array_replace($intermediadores, $resultadoLote);
        }

        return $intermediadores;
    }

    private function documentoFornecedor($fornecedor): string
    {
        if (! $fornecedor) {
            return '';
        }

        $attrs = method_exists($fornecedor, 'getAttributes') ? $fornecedor->getAttributes() : (array) $fornecedor;
        foreach (['Cnpj', 'CNPJ', 'Cpf', 'CPF', 'Cgc', 'CGC'] as $campo) {
            if (array_key_exists($campo, $attrs) && ! empty($attrs[$campo])) {
                return (string) $attrs[$campo];
            }
        }

        return '';
    }

    private function nomeFornecedor($fornecedor): string
    {
        if (! $fornecedor) {
            return '';
        }

        $attrs = method_exists($fornecedor, 'getAttributes') ? $fornecedor->getAttributes() : (array) $fornecedor;
        foreach (['Fornecedor', 'Nome conhecido', 'Nome cliente', 'Nome', 'Razão social', 'Razao social', 'Fantasia'] as $campo) {
            if (array_key_exists($campo, $attrs) && ! empty($attrs[$campo])) {
                return (string) $attrs[$campo];
            }
        }

        return '';
    }

    private function getRecordAttributeKeys(mixed $record): array
    {
        if ($record instanceof Model) {
            return array_keys($record->getAttributes());
        }

        if (is_object($record)) {
            if (method_exists($record, 'getAttributes')) {
                return array_keys($record->getAttributes());
            }

            return array_keys(get_object_vars($record));
        }

        if (is_array($record)) {
            return array_keys($record);
        }

        return [];
    }

    private function sanitizeDownloadFilename(string $filename, string $default = 'exportacao.xlsx'): string
    {
        $sanitized = trim($filename);
        $sanitized = str_replace(['\\', '/'], '_', $sanitized);
        $sanitized = preg_replace('/[\x00-\x1F\x7F]+/u', '', $sanitized) ?? '';
        $sanitized = preg_replace('/\s+/', '_', $sanitized) ?? '';
        $sanitized = preg_replace('/_+/', '_', $sanitized) ?? '';
        $sanitized = trim($sanitized, '._- ');

        return $sanitized !== '' ? $sanitized : $default;
    }

    private function downloadExcelFromCsvCallback(callable $callback, string $filename)
    {
        $filename = $this->sanitizeDownloadFilename(
            $filename,
            'exportacao_'.date('Y-m-d').'.xlsx'
        );

        ob_start();
        $callback();
        $csvContent = ob_get_clean();

        if (str_starts_with($csvContent, "\xEF\xBB\xBF")) {
            $csvContent = substr($csvContent, 3);
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csvContent);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $coordinate = Coordinate::stringFromColumnIndex($colIndex + 1).($rowIndex + 1);
                $numericCell = $this->parseSpreadsheetLocalizedNumber($value);

                if ($numericCell !== null) {
                    $sheet->setCellValueExplicit($coordinate, $numericCell['value'], DataType::TYPE_NUMERIC);
                    $sheet->getStyle($coordinate)
                        ->getNumberFormat()
                        ->setFormatCode($numericCell['format']);

                    continue;
                }

                $sheet->setCellValueExplicit($coordinate, (string) $value, DataType::TYPE_STRING);
            }
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_');
        $xlsxPath = $tempPath.'.xlsx';
        rename($tempPath, $xlsxPath);

        $writer = new Xlsx($spreadsheet);
        $writer->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->download($xlsxPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function parseSpreadsheetLocalizedNumber(mixed $value): ?array
    {
        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        if (! preg_match('/^-?(?:\d{1,3}(?:\.\d{3})*|\d+),\d+$/', $stringValue)) {
            return null;
        }

        $normalizedValue = str_replace('.', '', $stringValue);
        $normalizedValue = str_replace(',', '.', $normalizedValue);

        if (! is_numeric($normalizedValue)) {
            return null;
        }

        $decimalPlaces = strlen((string) Str::after($stringValue, ','));
        $formatCode = '#,##0'.($decimalPlaces > 0 ? '.'.str_repeat('0', $decimalPlaces) : '');

        return [
            'value' => (float) $normalizedValue,
            'format' => $formatCode,
        ];
    }

    private function formatContabilExportValue(string|int $column, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! is_numeric($value)) {
            return (string) $value;
        }

        $normalizedColumn = Str::of((string) $column)->ascii()->lower()->value();

        if (! $this->shouldFormatContabilDecimalColumn($normalizedColumn)) {
            return (string) $value;
        }

        return number_format(
            (float) $value,
            $this->inferContabilDecimalPlaces($normalizedColumn, $value),
            ',',
            '.'
        );
    }

    private function shouldFormatContabilDecimalColumn(string $normalizedColumn): bool
    {
        foreach ([
            'valor',
            'total',
            'desconto',
            'descto',
            'frete',
            'seguro',
            'despesa',
            'comissao',
            'base',
            'peso',
            'qtd',
            'qtde',
            'quantidade',
            'preco',
            'unitario',
            'tributo',
            'icms',
            'ipi',
            'pis',
            'cofins',
            'fcp',
            'custo',
        ] as $keyword) {
            if (str_contains($normalizedColumn, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function inferContabilDecimalPlaces(string $normalizedColumn, mixed $value): int
    {
        $variableScaleColumn = str_contains($normalizedColumn, 'qtd')
            || str_contains($normalizedColumn, 'qtde')
            || str_contains($normalizedColumn, 'quantidade')
            || str_contains($normalizedColumn, 'peso');

        if (! $variableScaleColumn) {
            return 2;
        }

        return fmod((float) $value, 1.0) === 0.0 ? 0 : 2;
    }

    // =========================================================================
    // EXPORTAÇÕES CONTÁBEIS (CSV)
    // =========================================================================

    /**
     * Exportar auditoria: Peças - Vendas
     * Retorna um CSV com os pedidos (Vendas sem NF) e as notas fiscais (Notasnf VENDA)
     */
    public function exportarContabilPecasVendas(Request $request)
    {
        [$dataInicio, $dataFim] = $this->resolverPeriodoExportacao($request);
        $tipoDados = $request->get('tipo_dados', 'faturamento'); // 'faturamento' ou 'pedidos'
        $filial = $this->normalizarFilial($request->get('filial'));

        if (! $dataInicio || ! $dataFim) {
            return back()->with('error', 'Período é obrigatório');
        }

        $cfopsFaturamento = [5405, 5102, 6102, 6108, 6404, 5117, 5656, 6110, 6117, 6119, 6403, 6656];
        $cfopsSaidas = [1202, 1411, 5908, 6908, 5916, 6916, 6949, 5949, 5905, 5906];

        $pedidos = null;
        $notas = null;
        $codigosClientes = collect();

        if ($tipoDados === 'pedidos') {
            $pedidos = DB::table('Vendas')
                ->where('No nf', 0)
                ->whereBetween('Data emissão', [$dataInicio, $dataFim])
                ->when($filial, fn ($q) => $q->where('Filial', $filial))
                ->orderBy('Data emissão', 'desc')
                ->get();
            $codigosClientes = $codigosClientes->merge($pedidos->pluck('Codigo cliente')->all());
        } elseif ($tipoDados === 'saidas') {
            $notas = DB::table('Notasnf')
                ->whereBetween('Data emissao nfe', [$dataInicio, $dataFim])
                ->where('Tipo notanf', 'VENDA')
                ->whereIn('Código natureza', $cfopsSaidas)
                ->where('Cancelada', 0)
                ->when($filial, fn ($q) => $q->where('Filial', $filial))
                ->orderBy('Código natureza')
                ->orderBy('Data emissao nfe', 'desc')
                ->get();
            $codigosClientes = $codigosClientes->merge($notas->pluck('Codigo cliente')->all())
                ->merge($notas->pluck('Código cliente')->all());
        } else {
            $notas = DB::table('Notasnf')
                ->whereBetween('Data emissao nfe', [$dataInicio, $dataFim])
                ->where('Tipo notanf', 'VENDA')
                ->whereIn('Código natureza', $cfopsFaturamento)
                ->where('Cancelada', 0)
                ->where('Remessa', 0)
                ->where('Denegada', 0)
                ->where('Nfe entrega', 0)
                ->where('Imobilizado', 0)
                ->when($filial, fn ($q) => $q->where('Filial', $filial))
                ->orderBy('Código natureza')
                ->orderBy('Data emissao nfe', 'desc')
                ->get();
            $codigosClientes = $codigosClientes->merge($notas->pluck('Codigo cliente')->all())
                ->merge($notas->pluck('Código cliente')->all());
        }

        $codigosClientes = $codigosClientes->filter(fn ($v) => ! is_null($v) && $v !== '')
            ->unique()
            ->values()
            ->all();
        $clientesMap = $this->buscarClientesPorCodigo($codigosClientes);

        $intermediadoresNotas = collect();
        if ($tipoDados !== 'pedidos' && $notas && $notas->count() > 0) {
            $numerosVendaNotas = collect($notas)
                ->map(function ($row) {
                    return $row->{'No venda'} ?? $row->{'No venda origem'} ?? null;
                })
                ->filter(fn ($v) => ! is_null($v) && $v !== '')
                ->unique()
                ->values()
                ->all();

            if (! empty($numerosVendaNotas)) {
                $intermediadoresNotas = collect($this->buscarIntermediadoresPorNumeroVenda($numerosVendaNotas));
            }
        }

        $nomeArquivo = 'faturamento_qtde';
        if ($tipoDados === 'pedidos') {
            $nomeArquivo = 'pedidos_sem_nf';
        } elseif ($tipoDados === 'saidas') {
            $nomeArquivo = 'saidas';
        }
        $filename = "auditoria_pecas_vendas_{$nomeArquivo}_".date('Y-m-d').'.xlsx';
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($pedidos, $notas, $dataInicio, $dataFim, $clientesMap, $tipoDados, $intermediadoresNotas, $filial) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            $titulo = 'Auditoria Contábil - Peças Vendas (Faturado e Qtde)';
            if ($tipoDados === 'pedidos') {
                $titulo = 'Auditoria Contábil - Peças Vendas (Pedidos Sem NF)';
            } elseif ($tipoDados === 'saidas') {
                $titulo = 'Auditoria Contábil - Peças Vendas (Apenas Saídas)';
            }
            fputcsv($file, [$titulo]);
            fputcsv($file, ['Período: '.date('d/m/Y', strtotime($dataInicio)).' a '.date('d/m/Y', strtotime($dataFim))]);
            if ($filial) {
                fputcsv($file, ['Filtro de Filial aplicado: '.$filial]);
            }
            fputcsv($file, ['Gerado em: '.date('d/m/Y H:i:s')]);
            fputcsv($file, []);

            if ($tipoDados === 'pedidos') {
                fputcsv($file, ['Total: '.$pedidos->count().' registros']);
                fputcsv($file, []);
                if ($pedidos->count() > 0) {
                    $attrs = array_keys((array) $pedidos->first());
                    $primeiraColuna = array_shift($attrs);
                    $colunas = array_merge([$primeiraColuna, 'Nome Cliente', 'Documento Cliente'], $attrs);
                    if (! in_array('Intermediador', $colunas, true)) {
                        $colunas = array_merge([$primeiraColuna, 'Nome Cliente', 'Documento Cliente', 'Intermediador'], $attrs);
                    }
                    fputcsv($file, $colunas);
                    foreach ($pedidos as $row) {
                        $rowArray = (array) $row;
                        $codigoCliente = (string) ($rowArray['Codigo cliente'] ?? $rowArray['Código cliente'] ?? '');
                        $nomeCliente = $clientesMap[$codigoCliente]['nome'] ?? ($rowArray['Cliente'] ?? '');
                        $documentoCliente = $clientesMap[$codigoCliente]['documento'] ?? ($rowArray['Cnpj'] ?? $rowArray['Cpf'] ?? '');

                        $linha = [];
                        foreach ($colunas as $coluna) {
                            if ($coluna === 'Nome Cliente') {
                                $linha[] = $nomeCliente;

                                continue;
                            }
                            if ($coluna === 'Documento Cliente') {
                                $linha[] = $documentoCliente;

                                continue;
                            }
                            if ($coluna === 'Intermediador') {
                                $linha[] = $rowArray['Intermediador'] ?? '';

                                continue;
                            }
                            $linha[] = $this->formatContabilExportValue($coluna, $rowArray[$coluna] ?? '');
                        }
                        fputcsv($file, $linha);
                    }
                } else {
                    fputcsv($file, ['Nenhum pedido encontrado no período.']);
                }
            } else {
                fputcsv($file, ['Total: '.$notas->count().' registros']);
                fputcsv($file, []);
                if ($notas->count() > 0) {
                    $currentCfop = null;
                    foreach ($notas as $row) {
                        if ($currentCfop !== $row->{'Código natureza'}) {
                            if ($currentCfop !== null) {
                                fputcsv($file, []);
                            }
                            $currentCfop = $row->{'Código natureza'};
                            fputcsv($file, ["CFOP {$currentCfop}"]);
                            $attrs = array_keys((array) $row);
                            $primeiraColuna = array_shift($attrs);
                            $colunas = array_merge([$primeiraColuna, 'Intermediador'], $attrs);
                            fputcsv($file, $colunas);
                        }
                        $rowArray = (array) $row;
                        $numeroVenda = $rowArray['No venda'] ?? $rowArray['No venda origem'] ?? null;
                        $intermediador = $numeroVenda ? ($intermediadoresNotas[$numeroVenda] ?? '') : '';

                        $linha = [];
                        foreach ($colunas as $coluna) {
                            if ($coluna === 'Intermediador') {
                                $linha[] = $intermediador;

                                continue;
                            }
                            $linha[] = $this->formatContabilExportValue($coluna, $rowArray[$coluna] ?? '');
                        }
                        fputcsv($file, $linha);
                    }
                } else {
                    fputcsv($file, ['Nenhuma nota fiscal encontrada no período.']);
                }
            }

            fclose($file);
        };

        return $this->downloadExcelFromCsvCallback($callback, $filename);
    }

    /**
     * Exportar auditoria: Peças - Ordens
     * Retorna um CSV com as notas fiscais (Notasnf != VENDA)
     */
    public function exportarContabilPecasOrdens(Request $request)
    {
        [$dataInicio, $dataFim] = $this->resolverPeriodoExportacao($request);
        $filial = $this->normalizarFilial($request->get('filial'));

        if (! $dataInicio || ! $dataFim) {
            return back()->with('error', 'Período é obrigatório');
        }

        $cfops = [5405, 5102, 6102, 6108, 6404, 5117, 5656, 6110, 6117, 6119, 6403, 6656];

        $notas = DB::table('Notasnf')
            ->whereBetween('Data emissao nfe', [$dataInicio, $dataFim])
            ->where('Tipo notanf', '!=', 'VENDA')
            ->whereIn('Código natureza', $cfops)
            ->where('Cancelada', 0)
            ->where('Remessa', 0)
            ->where('Denegada', 0)
            ->where('Nfe entrega', 0)
            ->where('Imobilizado', 0)
            ->when($filial, fn ($q) => $q->where('Filial', $filial))
            ->orderBy('Código natureza')
            ->orderBy('Data emissao nfe', 'desc')
            ->get();

        $filename = 'auditoria_pecas_ordens_'.date('Y-m-d').'.xlsx';
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($notas, $dataInicio, $dataFim, $filial) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['Auditoria Contábil - Peças Ordens']);
            fputcsv($file, ['Período: '.date('d/m/Y', strtotime($dataInicio)).' a '.date('d/m/Y', strtotime($dataFim))]);
            if ($filial) {
                fputcsv($file, ['Filtro de Filial aplicado: '.$filial]);
            }
            fputcsv($file, ['Gerado em: '.date('d/m/Y H:i:s')]);
            fputcsv($file, ['Total de Registros: '.$notas->count()]);
            fputcsv($file, []);

            if ($notas->count() > 0) {
                $currentCfop = null;
                foreach ($notas as $row) {
                    if ($currentCfop !== $row->{'Código natureza'}) {
                        if ($currentCfop !== null) {
                            fputcsv($file, []);
                        }
                        $currentCfop = $row->{'Código natureza'};
                        fputcsv($file, ["CFOP {$currentCfop}"]);
                        fputcsv($file, array_keys((array) $row));
                    }
                    $rowArray = (array) $row;
                    $linha = [];
                    foreach ($rowArray as $coluna => $valor) {
                        $linha[] = $this->formatContabilExportValue($coluna, $valor);
                    }
                    fputcsv($file, $linha);
                }
            } else {
                fputcsv($file, ['Nenhum registro encontrado para os filtros selecionados']);
            }

            fclose($file);
        };

        return $this->downloadExcelFromCsvCallback($callback, $filename);
    }

    /**
     * Exportar auditoria: Serviços - Ordens
     * Retorna um CSV com os registros importados manualmente
     */
    public function exportarContabilServicosOrdens(Request $request)
    {
        [$dataInicio, $dataFim] = $this->resolverPeriodoExportacao($request);
        $filial = $this->normalizarFilial($request->get('filial'));

        if (! $dataInicio || ! $dataFim) {
            return back()->with('error', 'Período é obrigatório');
        }

        $itens = ServicosImportacaoItem::on('tecnoar_api')
            ->with('importacao')
            ->whereBetween('data', [$dataInicio, $dataFim])
            ->orderBy('data', 'desc')
            ->get();

        $filename = 'auditoria_servicos_ordens_'.date('Y-m-d').'.xlsx';
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($itens, $dataInicio, $dataFim, $filial) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['Auditoria Contábil - Serviços Ordens']);
            fputcsv($file, ['Período: '.date('d/m/Y', strtotime($dataInicio)).' a '.date('d/m/Y', strtotime($dataFim))]);
            if ($filial) {
                fputcsv($file, ['Filtro de Filial informado: '.$filial.' (não aplicável nesta auditoria)']);
            }
            fputcsv($file, ['Gerado em: '.date('d/m/Y H:i:s')]);
            fputcsv($file, ['Total de Registros: '.$itens->count()]);
            fputcsv($file, []);

            fputcsv($file, ['Data', 'Cliente', 'Documento Cliente', 'Referência', 'Descrição', 'Quantidade', 'Valor Faturado', 'Arquivo Origem', 'Importado em']);

            foreach ($itens as $item) {
                fputcsv($file, [
                    $item->data ? $item->data->format('d/m/Y') : '',
                    $item->cliente ?? '',
                    '',
                    $item->referencia ?? '',
                    $item->descricao ?? '',
                    $item->quantidade,
                    number_format($item->valor_faturado, 2, ',', '.'),
                    $item->importacao ? $item->importacao->nome_arquivo : '',
                    $item->created_at ? $item->created_at->format('d/m/Y H:i:s') : '',
                ]);
            }

            if ($itens->count() === 0) {
                fputcsv($file, ['Nenhum registro encontrado para os filtros selecionados']);
            }

            fclose($file);
        };

        return $this->downloadExcelFromCsvCallback($callback, $filename);
    }

    /**
     * Exportar auditoria: Despesas - Compras
     * Retorna um CSV com as entradas de peças filtradas por CFOP
     */
    public function exportarContabilCompras(Request $request)
    {
        [$dataInicio, $dataFim] = $this->resolverPeriodoExportacao($request);
        $filial = $this->normalizarFilial($request->get('filial'));

        if (! $dataInicio || ! $dataFim) {
            return back()->with('error', 'Período é obrigatório');
        }

        $cfops = ['1102', '1403', '1652', '2102', '2403', '2652'];

        $registros = DB::table('Entradas peças as E')
            ->join('Natureza operação as N', 'E.Natureza operação', '=', 'N.Descrição natureza')
            ->whereBetween('E.Data entrada', [$dataInicio, $dataFim])
            ->whereIn('N.Código natureza', $cfops)
            ->when($filial, fn ($q) => $q->where('E.Filial', $filial))
            ->select('E.*', 'N.Código natureza as CFOP')
            ->orderBy('N.Código natureza')
            ->orderBy('E.Data entrada', 'desc')
            ->get();

        $filename = 'auditoria_despesas_compras_'.date('Y-m-d').'.xlsx';
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($registros, $dataInicio, $dataFim, $filial) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['Auditoria Contábil - Despesas Compras']);
            fputcsv($file, ['Período: '.date('d/m/Y', strtotime($dataInicio)).' a '.date('d/m/Y', strtotime($dataFim))]);
            if ($filial) {
                fputcsv($file, ['Filtro de Filial aplicado: '.$filial]);
            }
            fputcsv($file, ['Gerado em: '.date('d/m/Y H:i:s')]);
            fputcsv($file, ['Total de Registros: '.$registros->count()]);
            fputcsv($file, []);

            if ($registros->count() > 0) {
                $currentCfop = null;
                foreach ($registros as $row) {
                    if ($currentCfop !== $row->CFOP) {
                        if ($currentCfop !== null) {
                            fputcsv($file, []); // Linha em branco entre grupos
                        }
                        $currentCfop = $row->CFOP;
                        fputcsv($file, ["CFOP {$currentCfop}"]);
                        fputcsv($file, array_keys((array) $row));
                    }
                    $rowArray = (array) $row;
                    $linha = [];
                    foreach ($rowArray as $coluna => $valor) {
                        $linha[] = $this->formatContabilExportValue($coluna, $valor);
                    }
                    fputcsv($file, $linha);
                }
            } else {
                fputcsv($file, ['Nenhum registro encontrado para os filtros selecionados']);
            }

            fclose($file);
        };

        return $this->downloadExcelFromCsvCallback($callback, $filename);
    }

    // =========================================================================
    // IMPORTAÇÃO DE SERVIÇOS (CSV)
    // =========================================================================

    /**
     * Importar planilha CSV de Serviços - Ordens
     * Formato esperado: data, cliente, referencia, descricao, quantidade, valor_faturado
     * A primeira linha deve ser o cabeçalho.
     */
    public function importarServicosOrdens(Request $request)
    {
        $request->validate([
            'arquivo' => 'required|file|mimes:csv,txt|max:5120',
            'periodo_inicio' => 'required|date',
            'periodo_fim' => 'required|date|after_or_equal:periodo_inicio',
        ]);

        $arquivo = $request->file('arquivo');
        $caminho = $arquivo->store('servicos_ordens', 'local');

        // Criar registro de importação na conexão tecnoar_api
        $importacao = ServicosImportacao::on('tecnoar_api')->create([
            'periodo_inicio' => $request->input('periodo_inicio'),
            'periodo_fim' => $request->input('periodo_fim'),
            'nome_arquivo' => $arquivo->getClientOriginalName(),
            'caminho_arquivo' => $caminho,
            'user_id' => auth()->id(),
        ]);

        // Ler e processar o arquivo CSV
        $handle = fopen(Storage::disk('local')->path($caminho), 'r');
        $cabecalho = null;
        $totalImportados = 0;
        $erros = [];

        while (($linha = fgetcsv($handle, 1000, ';')) !== false) {
            // Tenta também com vírgula como separador
            if (count($linha) === 1) {
                $linha = str_getcsv($linha[0], ',');
            }

            // Primeira linha = cabeçalho
            if ($cabecalho === null) {
                $cabecalho = array_map('trim', $linha);

                continue;
            }

            if (count($linha) < 2) {
                continue;
            }

            // Montar mapa coluna => valor
            $dadosOriginais = [];
            foreach ($cabecalho as $idx => $col) {
                $dadosOriginais[$col] = $linha[$idx] ?? null;
            }

            // Tentar mapear campos automaticamente (aceita variações)
            $dataRaw = $dadosOriginais['data'] ?? $dadosOriginais['Data'] ?? $dadosOriginais['DATA'] ?? ($linha[0] ?? null);
            $dataConvertida = null;
            if ($dataRaw) {
                try {
                    $dataConvertida = Carbon::createFromFormat('d/m/Y', trim($dataRaw))->format('Y-m-d');
                } catch (\Exception $e) {
                    try {
                        $dataConvertida = Carbon::parse(trim($dataRaw))->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $erros[] = 'Linha inválida (data não reconhecida): '.implode(';', $linha);

                        continue;
                    }
                }
            }

            $valorRaw = $dadosOriginais['valor_faturado'] ?? $dadosOriginais['Valor'] ?? $dadosOriginais['valor'] ?? ($linha[5] ?? 0);
            $valor = (float) str_replace(['.', ','], ['', '.'], trim($valorRaw));

            $qtdRaw = $dadosOriginais['quantidade'] ?? $dadosOriginais['Qtde'] ?? $dadosOriginais['qtd'] ?? ($linha[4] ?? 1);
            $qtd = (int) trim($qtdRaw) ?: 1;

            ServicosImportacaoItem::on('tecnoar_api')->create([
                'importacao_id' => $importacao->id,
                'data' => $dataConvertida,
                'cliente' => trim($dadosOriginais['cliente'] ?? $dadosOriginais['Cliente'] ?? ($linha[1] ?? '')),
                'referencia' => trim($dadosOriginais['referencia'] ?? $dadosOriginais['Referência'] ?? ($linha[2] ?? '')),
                'descricao' => trim($dadosOriginais['descricao'] ?? $dadosOriginais['Descrição'] ?? ($linha[3] ?? '')),
                'quantidade' => $qtd,
                'valor_faturado' => $valor,
                'dados_originais' => $dadosOriginais,
            ]);

            $totalImportados++;
        }

        fclose($handle);

        $mensagem = "Importação concluída: {$totalImportados} registros importados.";
        if (count($erros) > 0) {
            $mensagem .= ' '.count($erros).' linha(s) ignorada(s) por erro de formato.';
        }

        return back()->with('success', $mensagem);
    }
}
