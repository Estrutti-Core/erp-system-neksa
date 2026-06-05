@extends('layouts.app2')

@section('content')
    <div class="space-y-6 max-w-7xl mx-auto">
        <div class="space-y-2">
            <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 mt-6">Análise de Clientes para Prospecção</h1>
            <p class="text-sm md:text-base text-gray-600 max-w-2xl">Painel pensado para priorizar segmentos, regiões e perfis de clientes com maior potencial. Ajuste filtros e foque onde a conversão é mais provável.</p>
            <div class="flex items-center gap-3 flex-wrap">
                <a href="{{ route('clientes.cnaes.analytics.export.csv', request()->query()) }}"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-sm bg-white hover:bg-gray-50">Exportar CSV</a>
                <span class="text-xs text-gray-500">Base filtrada • {{ number_format($totalProfiles, 0, ',', '.') }} registros</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 space-y-4">
                <details class="bg-white rounded-xl shadow-sm border border-gray-200 p-4" open>
                    <summary class="cursor-pointer text-sm font-semibold text-gray-800">Filtros de Prospecção</summary>
                    @php
                        $selectedCnae = request('cnae') ?: request('cnae_principal') ?: request('cnae_secundario');
                    @endphp
                    <form method="GET" action="{{ route('clientes.cnaes.analytics') }}" class="mt-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                        <select name="uf" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <option value="">UF</option>
                            @foreach($filterOptions['ufs'] as $uf)
                                <option value="{{ $uf }}" @selected(request('uf') === $uf)>{{ $uf }}</option>
                            @endforeach
                        </select>

                        <select name="municipio" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <option value="">Município</option>
                            @foreach($filterOptions['municipios'] as $municipio)
                                <option value="{{ $municipio }}" @selected(request('municipio') === $municipio)>{{ $municipio }}</option>
                            @endforeach
                        </select>

                        <select name="setor" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <option value="">Setor</option>
                            @foreach($filterOptions['setores'] as $setor)
                                <option value="{{ $setor }}" @selected(request('setor') === $setor)>{{ $setor }}</option>
                            @endforeach
                        </select>

                        <select name="porte" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <option value="">Porte</option>
                            @foreach($filterOptions['portes'] as $porte)
                                <option value="{{ $porte }}" @selected(request('porte') === $porte)>{{ $porte }}</option>
                            @endforeach
                        </select>

                        <select name="situacao" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <option value="">Situação</option>
                            @foreach($filterOptions['situacoes'] as $situacao)
                                <option value="{{ $situacao }}" @selected(request('situacao') === $situacao)>{{ $situacao }}</option>
                            @endforeach
                        </select>

                        <select name="natureza_juridica" class="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <option value="">Natureza Jurídica</option>
                            @foreach($filterOptions['naturezas'] as $natureza)
                                <option value="{{ $natureza }}" @selected(request('natureza_juridica') === $natureza)>{{ $natureza }}</option>
                            @endforeach
                        </select>

                        <label class="space-y-1">
                            <span class="text-xs font-medium text-gray-600">CNAE (principal ou secundário)</span>
                            <select name="cnae" id="cnae-filter"
                                class="w-full h-10 px-3 py-2 border border-gray-200 rounded-lg text-sm">
                                <option value=""></option>
                                @foreach($filterOptions['cnaes'] as $cnae)
                                    <option value="{{ $cnae->codigo }}" @selected((string) $selectedCnae === (string) $cnae->codigo)>
                                        {{ $cnae->codigo }}{{ $cnae->descricao ? ' - ' . $cnae->descricao : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="space-y-1">
                            <span class="text-xs font-medium text-gray-600">Data de abertura a partir de</span>
                            <input type="date" name="data_abertura_from" value="{{ request('data_abertura_from') }}"
                                class="w-full h-10 px-3 py-2 border border-gray-200 rounded-lg text-sm" />
                        </label>
                        <label class="space-y-1">
                            <span class="text-xs font-medium text-gray-600">Data de abertura até</span>
                            <input type="date" name="data_abertura_to" value="{{ request('data_abertura_to') }}"
                                class="w-full h-10 px-3 py-2 border border-gray-200 rounded-lg text-sm" />
                        </label>

                        <label class="space-y-1">
                            <span class="text-xs font-medium text-gray-600">Capital social mínimo</span>
                            <input name="capital_social_min" value="{{ request('capital_social_min') }}" placeholder="Ex.: 50000"
                                class="w-full h-10 px-3 py-2 border border-gray-200 rounded-lg text-sm" />
                        </label>
                        <label class="space-y-1">
                            <span class="text-xs font-medium text-gray-600">Capital social máximo</span>
                            <input name="capital_social_max" value="{{ request('capital_social_max') }}" placeholder="Ex.: 250000"
                                class="w-full h-10 px-3 py-2 border border-gray-200 rounded-lg text-sm" />
                        </label>

                        <div class="flex items-center gap-2">
                            <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white text-sm">Aplicar</button>
                            <a href="{{ route('clientes.cnaes.analytics') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm">Limpar</a>
                        </div>
                    </form>
                </details>
            </div>

            <div class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <h2 class="text-sm font-semibold text-gray-800 mb-3">Resumo rápido</h2>
                    <div class="space-y-2 text-xs text-gray-600">
                        <div class="flex items-center justify-between">
                            <span>CNAE principal informado</span>
                            <span class="font-semibold text-gray-900">{{ number_format($totalWithCnaePrincipal, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Com CNAE secundário</span>
                            <span class="font-semibold text-gray-900">{{ number_format($totalWithSecondary, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>CNAEs principais únicos</span>
                            <span class="font-semibold text-gray-900">{{ number_format($uniqueCnaePrincipal, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        
        @php
            $totalBase = max(1, $totalProfiles);
            $principalMax = $topCnaePrincipal->max('total') ?: 1;
            $secondaryMax = $topCnaeSecondary->max('total') ?: 1;
            $setorMax = $bySetor->max('total') ?: 1;
            $ufMax = $byUf->max('total') ?: 1;
            $municipioMax = $byMunicipio->max('total') ?: 1;
            $anoMax = $byAno->max('total') ?: 1;
            $sortBy = $currentSortBy ?? request('sort_by');
            $sortDir = $currentSortDir ?? request('sort_dir');
            $defaultSortDirection = function (string $column) {
                return in_array($column, ['os', 'vendas'], true) ? 'desc' : 'asc';
            };
            $sortUrl = function (string $column) use ($sortBy, $sortDir, $defaultSortDirection) {
                $params = request()->except('page');
                $initialDirection = $defaultSortDirection($column);
                $alternateDirection = $initialDirection === 'desc' ? 'asc' : 'desc';

                if ($sortBy !== $column) {
                    $params['sort_by'] = $column;
                    $params['sort_dir'] = $initialDirection;
                } elseif ($sortDir === $initialDirection) {
                    $params['sort_by'] = $column;
                    $params['sort_dir'] = $alternateDirection;
                } elseif ($sortDir === $alternateDirection) {
                    unset($params['sort_by'], $params['sort_dir']);
                } else {
                    unset($params['sort_by'], $params['sort_dir']);
                }

                return route('clientes.cnaes.analytics', $params) . '#detalhamento';
            };
            $sortIcon = function (string $column) use ($sortBy, $sortDir) {
                if ($sortBy !== $column) {
                    return '↕';
                }

                return $sortDir === 'asc' ? '↑' : '↓';
            };
        @endphp

        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800">CNAEs</h2>
        </div>
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h2 class="text-sm font-semibold text-gray-800 mb-3">Top CNAE Principal</h2>
                <div class="space-y-3 max-h-64 overflow-y-auto pr-1">
                    @forelse($topCnaePrincipal as $row)
                        @php
                            $pct = $row->total * 100 / $totalBase;
                            $bar = $row->total * 100 / $principalMax;
                        @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-gray-700">{{ $row->codigo }} {{ $row->descricao ? '- ' . $row->descricao : '' }}</span>
                                <span class="text-gray-500">{{ number_format($row->total, 0, ',', '.') }} ({{ number_format($pct, 1, ',', '.') }}%)</span>
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-primary" style="width: {{ $bar }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Nenhum dado encontrado.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h2 class="text-sm font-semibold text-gray-800 mb-3">Top CNAE Secundário</h2>
                <div class="space-y-3 max-h-64 overflow-y-auto pr-1">
                    @forelse($topCnaeSecondary as $row)
                        @php
                            $pct = $row->total * 100 / $totalBase;
                            $bar = $row->total * 100 / $secondaryMax;
                        @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-gray-700">{{ $row->codigo }} {{ $row->descricao ? '- ' . $row->descricao : '' }}</span>
                                <span class="text-gray-500">{{ number_format($row->total, 0, ',', '.') }} ({{ number_format($pct, 1, ',', '.') }}%)</span>
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500" style="width: {{ $bar }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Nenhum dado encontrado.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800">Segmentação</h2>
        </div>
        <div class="grid grid-cols-1 xl:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h2 class="text-sm font-semibold text-gray-800 mb-3">Natureza Jurídica</h2>
                <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                    @forelse($byNatureza as $row)
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-700">{{ $row->natureza_juridica }}</span>
                            <span class="text-gray-500">{{ number_format($row->total, 0, ',', '.') }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Nenhum dado encontrado.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h2 class="text-sm font-semibold text-gray-800 mb-3">Setores mais fortes</h2>
                <div class="space-y-3 max-h-64 overflow-y-auto pr-1">
                    @forelse($bySetor as $row)
                        @php
                            $pct = $row->total * 100 / $totalBase;
                            $bar = $row->total * 100 / $setorMax;
                        @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-gray-700">{{ $row->setor }}</span>
                                <span class="text-gray-500">{{ number_format($row->total, 0, ',', '.') }} ({{ number_format($pct, 1, ',', '.') }}%)</span>
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-sky-500" style="width: {{ $bar }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Nenhum dado encontrado.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h2 class="text-sm font-semibold text-gray-800 mb-3">Porte</h2>
                <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                    @forelse($byPorte as $row)
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-700">{{ $row->porte }}</span>
                            <span class="text-gray-500">{{ number_format($row->total, 0, ',', '.') }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Nenhum dado encontrado.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h2 class="text-sm font-semibold text-gray-800 mb-3">Situação Cadastral</h2>
                <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                    @forelse($bySituacao as $row)
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-700">{{ $row->situacao }}</span>
                            <span class="text-gray-500">{{ number_format($row->total, 0, ',', '.') }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Nenhum dado encontrado.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800">Geografia</h2>
        </div>
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h2 class="text-sm font-semibold text-gray-800 mb-3">Distribuição por UF</h2>
                <div class="space-y-3 max-h-64 overflow-y-auto pr-1">
                    @forelse($byUf as $row)
                        @php
                            $pct = $row->total * 100 / $totalBase;
                            $bar = $row->total * 100 / $ufMax;
                        @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-gray-700">{{ $row->uf }}</span>
                                <span class="text-gray-500">{{ number_format($row->total, 0, ',', '.') }} ({{ number_format($pct, 1, ',', '.') }}%)</span>
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-violet-500" style="width: {{ $bar }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Nenhum dado encontrado.</p>
                    @endforelse
                </div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h2 class="text-sm font-semibold text-gray-800 mb-3">Top Municípios</h2>
                <div class="space-y-3 max-h-64 overflow-y-auto pr-1">
                    @forelse($byMunicipio as $row)
                        @php
                            $pct = $row->total * 100 / $totalBase;
                            $bar = $row->total * 100 / $municipioMax;
                        @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-gray-700">{{ $row->municipio }}</span>
                                <span class="text-gray-500">{{ number_format($row->total, 0, ',', '.') }} ({{ number_format($pct, 1, ',', '.') }}%)</span>
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-amber-500" style="width: {{ $bar }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Nenhum dado encontrado.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800">Evolução</h2>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <h2 class="text-sm font-semibold text-gray-800 mb-3">Abertura por Ano</h2>
            <div class="space-y-3 max-h-64 overflow-y-auto pr-1">
                @forelse($byAno as $row)
                    @php
                        $pct = $row->total * 100 / $totalBase;
                        $bar = $row->total * 100 / $anoMax;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-gray-700">{{ $row->ano }}</span>
                            <span class="text-gray-500">{{ number_format($row->total, 0, ',', '.') }} ({{ number_format($pct, 1, ',', '.') }}%)</span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-indigo-500" style="width: {{ $bar }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Nenhum dado encontrado.</p>
                @endforelse
            </div>
        </div>

        <div id="detalhamento" class="bg-white rounded-xl border border-gray-200 p-4">
            <h2 class="text-sm font-semibold text-gray-800 mb-3">Detalhamento (paginado)</h2>
            <div class="overflow-x-auto max-h-[520px] overflow-y-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-gray-50 text-gray-600 sticky top-0 z-10">
                        <tr>
                            <th class="text-left p-2 whitespace-nowrap">Cliente</th>
                            <th class="text-left p-2 whitespace-nowrap">
                                <a href="{{ $sortUrl('os') }}" class="inline-flex items-center gap-1 hover:text-gray-900">
                                    OS
                                    <span class="text-[11px] text-gray-400">{{ $sortIcon('os') }}</span>
                                </a>
                            </th>
                            <th class="text-left p-2 whitespace-nowrap">
                                <a href="{{ $sortUrl('vendas') }}" class="inline-flex items-center gap-1 hover:text-gray-900">
                                    Vendas
                                    <span class="text-[11px] text-gray-400">{{ $sortIcon('vendas') }}</span>
                                </a>
                            </th>
                            <th class="text-left p-2 whitespace-nowrap">CNPJ</th>
                            <th class="text-left p-2 whitespace-nowrap">
                                <a href="{{ $sortUrl('cnae_principal') }}" class="inline-flex items-center gap-1 hover:text-gray-900">
                                    CNAE Principal
                                    <span class="text-[11px] text-gray-400">{{ $sortIcon('cnae_principal') }}</span>
                                </a>
                            </th>
                            <th class="text-left p-2 whitespace-nowrap">
                                <a href="{{ $sortUrl('setor') }}" class="inline-flex items-center gap-1 hover:text-gray-900">
                                    Setor
                                    <span class="text-[11px] text-gray-400">{{ $sortIcon('setor') }}</span>
                                </a>
                            </th>
                            <th class="text-left p-2 whitespace-nowrap">
                                <a href="{{ $sortUrl('porte') }}" class="inline-flex items-center gap-1 hover:text-gray-900">
                                    Porte
                                    <span class="text-[11px] text-gray-400">{{ $sortIcon('porte') }}</span>
                                </a>
                            </th>
                            <th class="text-left p-2 whitespace-nowrap">
                                <a href="{{ $sortUrl('situacao') }}" class="inline-flex items-center gap-1 hover:text-gray-900">
                                    Situação
                                    <span class="text-[11px] text-gray-400">{{ $sortIcon('situacao') }}</span>
                                </a>
                            </th>
                            <th class="text-left p-2 whitespace-nowrap">
                                <a href="{{ $sortUrl('natureza') }}" class="inline-flex items-center gap-1 hover:text-gray-900">
                                    Natureza
                                    <span class="text-[11px] text-gray-400">{{ $sortIcon('natureza') }}</span>
                                </a>
                            </th>
                            <th class="text-left p-2 whitespace-nowrap">
                                <a href="{{ $sortUrl('municipio_uf') }}" class="inline-flex items-center gap-1 hover:text-gray-900">
                                    Município/UF
                                    <span class="text-[11px] text-gray-400">{{ $sortIcon('municipio_uf') }}</span>
                                </a>
                            </th>
                            <th class="text-left p-2 whitespace-nowrap">
                                <a href="{{ $sortUrl('capital') }}" class="inline-flex items-center gap-1 hover:text-gray-900">
                                    Capital
                                    <span class="text-[11px] text-gray-400">{{ $sortIcon('capital') }}</span>
                                </a>
                            </th>
                            <th class="text-left p-2 whitespace-nowrap">
                                <a href="{{ $sortUrl('secundarios') }}" class="inline-flex items-center gap-1 hover:text-gray-900">
                                    Secundários
                                    <span class="text-[11px] text-gray-400">{{ $sortIcon('secundarios') }}</span>
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($details as $row)
                            @php
                                $cliente = $clientes->get($row->cliente_id);
                                $clienteNome = $cliente->{'Nome conhecido'} ?? $cliente->{'Nome cliente'} ?? '---';
                            @endphp
                            <tr>
                                <td class="p-2 text-gray-800">
                                    <div class="font-medium">{{ $clienteNome }}</div>
                                    <div class="text-gray-500">#{{ $row->cliente_id }}</div>
                                </td>
                                <td class="p-2 text-gray-700">
                                    <span class="inline-flex w-fit items-center rounded-full bg-slate-100 px-2 py-1 text-[11px] text-slate-700">
                                        {{ number_format((int) ($row->total_ordens ?? 0), 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="p-2 text-gray-700">
                                    <span class="inline-flex w-fit items-center rounded-full bg-emerald-100 px-2 py-1 text-[11px] text-emerald-700">
                                        {{ number_format((int) ($row->total_vendas ?? 0), 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="p-2 text-gray-700">{{ $row->cnpj ?? '---' }}</td>
                                <td class="p-2 text-gray-700">
                                    {{ $row->cnae_principal_codigo ?? '---' }}
                                    {{ $row->cnae_principal_descricao ? '- ' . $row->cnae_principal_descricao : '' }}
                                </td>
                                <td class="p-2 text-gray-700">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] bg-slate-100 text-slate-700">
                                        {{ $row->setor ?? '---' }}
                                    </span>
                                </td>
                                <td class="p-2 text-gray-700">{{ $row->porte ?? '---' }}</td>
                                <td class="p-2 text-gray-700">{{ $row->situacao_cadastral ?? '---' }}</td>
                                <td class="p-2 text-gray-700">{{ $row->natureza_juridica ?? '---' }}</td>
                                <td class="p-2 text-gray-700">{{ $row->municipio ?? '---' }} / {{ $row->uf ?? '--' }}</td>
                                <td class="p-2 text-gray-700">R$ {{ number_format($row->capital_social ?? 0, 2, ',', '.') }}</td>
                                <td class="p-2 text-gray-700">{{ (int) ($row->cnaes_secundarios ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="p-4 text-center text-gray-500">Nenhum dado encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $details->links() }}
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        #cnae-filter + .select2-container .select2-selection--single {
            height: 2.5rem;
            border: 1px solid rgb(229 231 235);
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }

        #cnae-filter + .select2-container .select2-selection__rendered {
            line-height: 2.5rem;
            padding-left: 0.75rem;
            color: rgb(17 24 39);
        }

        #cnae-filter + .select2-container .select2-selection__arrow {
            height: 2.5rem;
            right: 0.5rem;
        }
    </style>
@endpush

@push('scripts')
    <script>
        $(function () {
            $('#cnae-filter').select2({
                placeholder: 'Selecione ou digite um CNAE',
                allowClear: true,
                width: '100%'
            });
        });
    </script>
@endpush
