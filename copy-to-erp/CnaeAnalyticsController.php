<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CnaeAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $conn = DB::connection('tecnoar_api');
        [$sortBy, $sortDir] = $this->resolveDetailsSort($request);

        $base = $this->baseProfilesQuery($request);
        $secondaryBase = $this->secondaryBaseQuery($request);

        $totalProfiles = (clone $base)->count();
        $totalWithCnaePrincipal = (clone $base)->whereNotNull('p.cnae_principal_codigo')->count();
        $totalWithSecondary = (clone $base)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('cliente_cnpj_secundary_cnaes as s')
                    ->whereColumn('s.cliente_cnpj_profile_id', 'p.id');
            })
            ->count();

        $uniqueCnaePrincipal = (clone $base)
            ->whereNotNull('p.cnae_principal_codigo')
            ->distinct()
            ->count('p.cnae_principal_codigo');

        $uniqueCnaeSecondary = (clone $secondaryBase)
            ->distinct()
            ->count('s.cnae_codigo');

        $topCnaePrincipal = (clone $base)
            ->leftJoin('cnaes as c', 'c.codigo', '=', 'p.cnae_principal_codigo')
            ->whereNotNull('p.cnae_principal_codigo')
            ->select(
                'p.cnae_principal_codigo as codigo',
                'c.descricao',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('p.cnae_principal_codigo', 'c.descricao')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $topCnaeSecondary = (clone $secondaryBase)
            ->leftJoin('cnaes as c', 'c.codigo', '=', 's.cnae_codigo')
            ->select(
                's.cnae_codigo as codigo',
                'c.descricao',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('s.cnae_codigo', 'c.descricao')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $bySetor = (clone $base)
            ->whereNotNull('p.setor')
            ->select('p.setor', DB::raw('COUNT(*) as total'))
            ->groupBy('p.setor')
            ->orderByDesc('total')
            ->get();

        $byPorte = (clone $base)
            ->whereNotNull('p.porte')
            ->select('p.porte', DB::raw('COUNT(*) as total'))
            ->groupBy('p.porte')
            ->orderByDesc('total')
            ->get();

        $bySituacao = (clone $base)
            ->whereNotNull('p.situacao_cadastral')
            ->select('p.situacao_cadastral as situacao', DB::raw('COUNT(*) as total'))
            ->groupBy('p.situacao_cadastral')
            ->orderByDesc('total')
            ->get();

        $byNatureza = (clone $base)
            ->whereNotNull('p.natureza_juridica')
            ->select('p.natureza_juridica', DB::raw('COUNT(*) as total'))
            ->groupBy('p.natureza_juridica')
            ->orderByDesc('total')
            ->get();

        $byUf = (clone $base)
            ->whereNotNull('p.uf')
            ->select('p.uf', DB::raw('COUNT(*) as total'))
            ->groupBy('p.uf')
            ->orderByDesc('total')
            ->get();

        $byMunicipio = (clone $base)
            ->whereNotNull('p.municipio')
            ->select('p.municipio', DB::raw('COUNT(*) as total'))
            ->groupBy('p.municipio')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $byAno = (clone $base)
            ->whereNotNull('p.data_abertura')
            ->select(DB::raw('YEAR(p.data_abertura) as ano'), DB::raw('COUNT(*) as total'))
            ->groupBy(DB::raw('YEAR(p.data_abertura)'))
            ->orderByDesc('total')
            ->orderByDesc('ano')
            ->get();

        $capitalStats = (clone $base)
            ->select(
                DB::raw('MIN(p.capital_social) as min'),
                DB::raw('MAX(p.capital_social) as max'),
                DB::raw('AVG(p.capital_social) as avg'),
                DB::raw('SUM(p.capital_social) as total')
            )
            ->first();

        $secondaryCountsSub = $conn->table('cliente_cnpj_secundary_cnaes as s')
            ->select('s.cliente_cnpj_profile_id', DB::raw('COUNT(*) as total'))
            ->groupBy('s.cliente_cnpj_profile_id');

        $ordensCountsSub = $this->ordensCountsSubquery();
        $vendasCountsSub = $this->vendasCountsSubquery();

        $detailsQuery = (clone $base)
            ->leftJoin('cnaes as c', 'c.codigo', '=', 'p.cnae_principal_codigo')
            ->leftJoinSub($secondaryCountsSub, 'sc', function ($join) {
                $join->on('sc.cliente_cnpj_profile_id', '=', 'p.id');
            })
            ->leftJoinSub($ordensCountsSub, 'oc', function ($join) {
                $join->on('oc.cliente_id', '=', 'p.cliente_id');
            })
            ->leftJoinSub($vendasCountsSub, 'vc', function ($join) {
                $join->on('vc.cliente_id', '=', 'p.cliente_id');
            })
            ->select(
                'p.id',
                'p.cliente_id',
                'p.cnpj',
                'p.cnae_principal_codigo',
                'c.descricao as cnae_principal_descricao',
                'p.setor',
                'p.porte',
                'p.situacao_cadastral',
                'p.natureza_juridica',
                'p.municipio',
                'p.uf',
                'p.capital_social',
                'p.data_abertura',
                'p.auto_filled_at',
                DB::raw('COALESCE(sc.total, 0) as cnaes_secundarios'),
                DB::raw('COALESCE(oc.total_ordens, 0) as total_ordens'),
                DB::raw('COALESCE(vc.total_vendas, 0) as total_vendas')
            );
        $details = $this->applyDetailsSorting($detailsQuery, $sortBy, $sortDir)
            ->paginate(50)
            ->appends($request->query());

        $clienteIds = collect($details->items())->pluck('cliente_id')->filter()->unique()->values();
        $clientes = collect();
        if ($clienteIds->isNotEmpty()) {
            $clientes = DB::connection('sqlsrv')
                ->table('Clientes')
                ->whereIn('Codigo cliente', $clienteIds)
                ->select('Codigo cliente', 'Nome conhecido', 'Nome cliente', 'Cnpj', 'Cpf')
                ->get()
                ->keyBy('Codigo cliente');
        }

        $filterOptions = [
            'ufs' => $conn->table('cliente_cnpj_profiles')->whereNotNull('uf')->distinct()->orderBy('uf')->pluck('uf'),
            'municipios' => $conn->table('cliente_cnpj_profiles')->whereNotNull('municipio')->distinct()->orderBy('municipio')->pluck('municipio'),
            'portes' => $conn->table('cliente_cnpj_profiles')->whereNotNull('porte')->distinct()->orderBy('porte')->pluck('porte'),
            'situacoes' => $conn->table('cliente_cnpj_profiles')->whereNotNull('situacao_cadastral')->distinct()->orderBy('situacao_cadastral')->pluck('situacao_cadastral'),
            'setores' => $conn->table('cliente_cnpj_profiles')->whereNotNull('setor')->distinct()->orderBy('setor')->pluck('setor'),
            'naturezas' => $conn->table('cliente_cnpj_profiles')->whereNotNull('natureza_juridica')->distinct()->orderBy('natureza_juridica')->pluck('natureza_juridica'),
            'cnaes' => $conn->table('cnaes')
                ->whereNotNull('codigo')
                ->select('codigo', 'descricao')
                ->orderBy('codigo')
                ->get(),
        ];

        return view('cnaes.analytics', [
            'totalProfiles' => $totalProfiles,
            'totalWithCnaePrincipal' => $totalWithCnaePrincipal,
            'totalWithSecondary' => $totalWithSecondary,
            'uniqueCnaePrincipal' => $uniqueCnaePrincipal,
            'uniqueCnaeSecondary' => $uniqueCnaeSecondary,
            'topCnaePrincipal' => $topCnaePrincipal,
            'topCnaeSecondary' => $topCnaeSecondary,
            'bySetor' => $bySetor,
            'byPorte' => $byPorte,
            'bySituacao' => $bySituacao,
            'byNatureza' => $byNatureza,
            'byUf' => $byUf,
            'byMunicipio' => $byMunicipio,
            'byAno' => $byAno,
            'capitalStats' => $capitalStats,
            'details' => $details,
            'clientes' => $clientes,
            'filterOptions' => $filterOptions,
            'currentSortBy' => $sortBy,
            'currentSortDir' => $sortDir,
        ]);
    }

    public function exportCsv(Request $request)
    {
        return $this->streamExport($request);
    }

    private function streamExport(Request $request)
    {
        $conn = DB::connection('tecnoar_api');
        [$sortBy, $sortDir] = $this->resolveDetailsSort($request);
        $base = $this->baseProfilesQuery($request);

        $secondaryCountsSub = $conn->table('cliente_cnpj_secundary_cnaes as s')
            ->select('s.cliente_cnpj_profile_id', DB::raw('COUNT(*) as total'))
            ->groupBy('s.cliente_cnpj_profile_id');

        $ordensCountsSub = $this->ordensCountsSubquery();
        $vendasCountsSub = $this->vendasCountsSubquery();

        $query = (clone $base)
            ->leftJoin('cnaes as c', 'c.codigo', '=', 'p.cnae_principal_codigo')
            ->leftJoinSub($secondaryCountsSub, 'sc', function ($join) {
                $join->on('sc.cliente_cnpj_profile_id', '=', 'p.id');
            })
            ->leftJoinSub($ordensCountsSub, 'oc', function ($join) {
                $join->on('oc.cliente_id', '=', 'p.cliente_id');
            })
            ->leftJoinSub($vendasCountsSub, 'vc', function ($join) {
                $join->on('vc.cliente_id', '=', 'p.cliente_id');
            })
            ->select(
                'p.id as id',
                'p.cliente_id',
                'p.cnpj',
                'p.cnae_principal_codigo',
                'c.descricao as cnae_principal_descricao',
                'p.setor',
                'p.porte',
                'p.situacao_cadastral',
                'p.natureza_juridica',
                'p.municipio',
                'p.uf',
                'p.capital_social',
                'p.data_abertura',
                DB::raw('COALESCE(sc.total, 0) as cnaes_secundarios'),
                DB::raw('COALESCE(oc.total_ordens, 0) as total_ordens'),
                DB::raw('COALESCE(vc.total_vendas, 0) as total_vendas')
            );
        $query = $this->applyDetailsSorting($query, $sortBy, $sortDir);

        $total = (clone $base)->count();
        $filename = 'cnaes_analytics_'.now()->format('Y-m-d').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($query, $total, $request) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, ['Analise de CNAEs - Prospecao']);
            fputcsv($file, ['Gerado em: '.date('d/m/Y H:i:s')]);
            fputcsv($file, ['Total de Registros: '.$total]);
            fputcsv($file, ['Filtros:', http_build_query($request->except('page'))]);
            fputcsv($file, []);

            $headers = [
                'Cliente ID',
                'Cliente Nome',
                'CNPJ',
                'CNAE Principal',
                'CNAE Descricao',
                'Setor',
                'Porte',
                'Situacao',
                'Natureza Juridica',
                'Municipio',
                'UF',
                'Capital Social',
                'Data Abertura',
                'CNAEs Secundarios',
            ];
            fputcsv($file, $headers);

            $query->chunk(500, function ($rows) use ($file) {
                $clienteIds = collect($rows)->pluck('cliente_id')->filter()->unique()->values();
                $clientes = collect();
                if ($clienteIds->isNotEmpty()) {
                    $clientes = DB::connection('sqlsrv')
                        ->table('Clientes')
                        ->whereIn('Codigo cliente', $clienteIds)
                        ->select('Codigo cliente', 'Nome conhecido', 'Nome cliente')
                        ->get()
                        ->keyBy('Codigo cliente');
                }

                foreach ($rows as $row) {
                    $cliente = $clientes->get($row->cliente_id);
                    $clienteNome = $cliente->{'Nome conhecido'} ?? $cliente->{'Nome cliente'} ?? '';

                    fputcsv($file, [
                        $row->cliente_id,
                        $clienteNome,
                        $row->cnpj,
                        $row->cnae_principal_codigo,
                        $row->cnae_principal_descricao,
                        $row->setor,
                        $row->porte,
                        $row->situacao_cadastral,
                        $row->natureza_juridica,
                        $row->municipio,
                        $row->uf,
                        $row->capital_social,
                        $row->data_abertura ? date('d/m/Y', strtotime($row->data_abertura)) : null,
                        $row->cnaes_secundarios,
                    ]);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function baseProfilesQuery(Request $request)
    {
        $query = DB::connection('tecnoar_api')->table('cliente_cnpj_profiles as p');

        return $this->applyFilters($query, $request, 'p');
    }

    private function secondaryBaseQuery(Request $request)
    {
        $query = DB::connection('tecnoar_api')
            ->table('cliente_cnpj_secundary_cnaes as s')
            ->join('cliente_cnpj_profiles as p', 'p.id', '=', 's.cliente_cnpj_profile_id');

        return $this->applyFilters($query, $request, 'p');
    }

    private function resolveDetailsSort(Request $request): array
    {
        $sortBy = $request->filled('sort_by') ? (string) $request->input('sort_by') : null;
        $sortableKeys = array_merge(
            array_keys($this->detailsSortableColumns()),
            ['municipio_uf', 'secundarios', 'os', 'vendas']
        );

        if ($sortBy !== null && ! in_array($sortBy, $sortableKeys, true)) {
            $sortBy = null;
        }

        $sortDir = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if ($sortBy === null) {
            $sortDir = null;
        }

        return [$sortBy, $sortDir];
    }

    private function detailsSortableColumns(): array
    {
        return [
            'cnae_principal' => 'p.cnae_principal_codigo',
            'setor' => 'p.setor',
            'porte' => 'p.porte',
            'situacao' => 'p.situacao_cadastral',
            'natureza' => 'p.natureza_juridica',
            'capital' => 'p.capital_social',
        ];
    }

    private function applyDetailsSorting($query, ?string $sortBy, ?string $sortDir)
    {
        if ($sortBy === null || $sortDir === null) {
            return $query
                ->orderByDesc('p.auto_filled_at')
                ->orderByDesc('p.id');
        }

        if ($sortBy === 'municipio_uf') {
            $query->orderBy('p.municipio', $sortDir)
                ->orderBy('p.uf', $sortDir);
        } elseif ($sortBy === 'secundarios') {
            $query->orderByRaw('COALESCE(sc.total, 0) '.$sortDir);
        } elseif ($sortBy === 'os') {
            $query->orderByRaw('COALESCE(oc.total_ordens, 0) '.$sortDir);
        } elseif ($sortBy === 'vendas') {
            $query->orderByRaw('COALESCE(vc.total_vendas, 0) '.$sortDir);
        } else {
            $columns = $this->detailsSortableColumns();
            if (isset($columns[$sortBy])) {
                $query->orderBy($columns[$sortBy], $sortDir);
            }
        }

        return $query
            ->orderByDesc('p.auto_filled_at')
            ->orderByDesc('p.id');
    }

    private function ordensCountsSubquery()
    {
        return DB::connection('tecnoar_api')
            ->query()
            ->from($this->sqlsrvQualifiedTable('Ordens', 'o'))
            ->selectRaw('o.[Codigo cliente] as cliente_id, COUNT(DISTINCT o.[Numero ordem]) as total_ordens')
            ->groupBy(DB::raw('o.[Codigo cliente]'));
    }

    private function vendasCountsSubquery()
    {
        return DB::connection('tecnoar_api')
            ->query()
            ->from($this->sqlsrvQualifiedTable('Vendas', 'v'))
            ->selectRaw('v.[Codigo cliente] as cliente_id, COUNT(DISTINCT v.[No venda]) as total_vendas')
            ->groupBy(DB::raw('v.[Codigo cliente]'));
    }

    private function sqlsrvQualifiedTable(string $table, string $alias)
    {
        $databaseName = str_replace(']', ']]', (string) DB::connection('sqlsrv')->getDatabaseName());

        return DB::raw(sprintf('[%s].[dbo].[%s] as %s', $databaseName, $table, $alias));
    }

    private function applyFilters($query, Request $request, string $alias = 'p')
    {
        $prefix = $alias ? $alias.'.' : '';

        if ($request->filled('q')) {
            $term = '%'.trim($request->q).'%';
            $query->where(function ($q) use ($term, $prefix) {
                $q->where($prefix.'cnpj', 'like', $term)
                    ->orWhere($prefix.'municipio', 'like', $term)
                    ->orWhere($prefix.'cnae_principal_codigo', 'like', $term)
                    ->orWhere($prefix.'cnae_principal_descricao', 'like', $term);
            });
        }

        if ($request->filled('uf')) {
            $query->where($prefix.'uf', $request->uf);
        }

        if ($request->filled('municipio')) {
            $query->where($prefix.'municipio', $request->municipio);
        }

        if ($request->filled('porte')) {
            $query->where($prefix.'porte', $request->porte);
        }

        if ($request->filled('situacao')) {
            $query->where($prefix.'situacao_cadastral', $request->situacao);
        }

        if ($request->filled('setor')) {
            $query->where($prefix.'setor', $request->setor);
        }

        if ($request->filled('natureza_juridica')) {
            $query->where($prefix.'natureza_juridica', $request->natureza_juridica);
        }

        if ($request->filled('cnae')) {
            $cnae = trim($request->cnae);
            $query->where(function ($q) use ($cnae, $prefix, $alias) {
                $q->where($prefix.'cnae_principal_codigo', $cnae)
                    ->orWhereExists(function ($sq) use ($cnae, $alias) {
                        $sq->select(DB::raw(1))
                            ->from('cliente_cnpj_secundary_cnaes as s')
                            ->whereColumn('s.cliente_cnpj_profile_id', $alias.'.id')
                            ->where('s.cnae_codigo', $cnae);
                    });
            });
        } else {
            if ($request->filled('cnae_principal')) {
                $query->where($prefix.'cnae_principal_codigo', $request->cnae_principal);
            }

            if ($request->filled('cnae_secundario')) {
                $cnaeSec = $request->cnae_secundario;
                $query->whereExists(function ($q) use ($cnaeSec, $alias) {
                    $q->select(DB::raw(1))
                        ->from('cliente_cnpj_secundary_cnaes as s')
                        ->whereColumn('s.cliente_cnpj_profile_id', $alias.'.id')
                        ->where('s.cnae_codigo', $cnaeSec);
                });
            }
        }

        if ($request->filled('data_abertura_from')) {
            $query->whereDate($prefix.'data_abertura', '>=', $request->data_abertura_from);
        }

        if ($request->filled('data_abertura_to')) {
            $query->whereDate($prefix.'data_abertura', '<=', $request->data_abertura_to);
        }

        if ($request->filled('capital_social_min')) {
            $query->where($prefix.'capital_social', '>=', (float) $request->capital_social_min);
        }

        if ($request->filled('capital_social_max')) {
            $query->where($prefix.'capital_social', '<=', (float) $request->capital_social_max);
        }

        return $query;
    }
}
