@extends('layouts.app2')

@section('content')
    <style>
        .filter-row-highlight th {
            background-color: #fef9c3 !important;
            /* bg-yellow-100 */
        }

        .dark-mode .filter-row-highlight th {
            background-color: rgba(234, 179, 8, 0.12) !important;
            border-top: 1px solid rgba(234, 179, 8, 0.2) !important;
            border-bottom: 1px solid rgba(234, 179, 8, 0.2) !important;
        }

        .rotas-table {
            width: 100%;
            table-layout: fixed;
        }

        .rotas-table th,
        .rotas-table td {
            overflow-wrap: anywhere;
            word-break: break-word;
            vertical-align: top;
        }

        .rotas-table thead input,
        .rotas-table thead select {
            min-width: 0;
        }

        .rotas-table .col-numero,
        .rotas-table .col-qtd {
            width: 10%;
            white-space: nowrap;
        }

        .rotas-table .col-data,
        .rotas-table .col-visita {
            width: 14%;
            white-space: nowrap;
        }

        .rotas-table .col-tecnico,
        .rotas-table .col-operador {
            width: 19%;
        }

        .rotas-table .col-acoes {
            width: 14%;
            white-space: nowrap;
        }

        .rotas-table-panel {
            max-height: none !important;
            overflow: visible !important;
            border-radius: 1.5rem !important;
            clip-path: inset(0 round 1.5rem);
        }

        .rotas-table thead tr:first-child th:first-child {
            border-top-left-radius: 1.5rem !important;
        }

        .rotas-table thead tr:first-child th:last-child {
            border-top-right-radius: 1.5rem !important;
        }

        .rotas-table tbody tr:last-child td:first-child {
            border-bottom-left-radius: 1.5rem !important;
        }

        .rotas-table tbody tr:last-child td:last-child {
            border-bottom-right-radius: 1.5rem !important;
        }

        .rotas-sticky-toolbar {
            position: sticky !important;
            top: 0 !important;
            z-index: 20 !important;
            background-color: #ffffff !important;
        }

        .dark-mode .rotas-sticky-toolbar,
        .dark .rotas-sticky-toolbar {
            background-color: #18181b !important;
        }
    </style>
    @php
        $roteirosJson = $roteiros
            ->map(function ($roteiro) {
                $firstItem = $roteiro->itens->first();
                $dataVisita = $firstItem ? $firstItem->{'Data visita'} : null;
                return [
                    'numero_roteiro' => $roteiro->{'Numero roteiro'},
                    'data_emissao' => \Carbon\Carbon::parse($roteiro->{'Data emissao'})->format('d/m/Y'),
                    'tecnico' => $roteiro->Tecnico,
                    'dia_visita' => $dataVisita ? \Carbon\Carbon::parse($dataVisita)->format('d/m/Y') : 'N/A',
                    'operador' => $roteiro->Operador,
                    'qtd_os' => $roteiro->itens->count(),
                    'edit_url' => route('rotas.edit', $roteiro->{'Numero roteiro'}),
                    'print_url' => route('rotas.pdf', $roteiro->{'Numero roteiro'}),
                ];
            })
            ->values();
    @endphp

    <div class="space-y-4 md:space-y-8 p-4 md:p-6 w-full min-w-0 max-w-full" x-data="rotasTable(@js($roteirosJson))">
        <!-- Header -->
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 ml-1 md:ml-4 mt-2">Roteiros Técnicos</h1>
                <p class="text-xs text-gray-400 font-medium ml-1 md:ml-4 uppercase tracking-widest mt-1">
                    Gestão de visitas e roteiros técnicos
                </p>
            </div>
            <div class="flex items-center gap-3 ml-1 md:ml-0">
                <a href="{{ route('rotas.mapa-geral') }}"
                    class="h-12 px-6 flex items-center justify-center rounded-full bg-white border border-amber-100 shadow-sm hover:bg-amber-50 hover:border-amber-200 text-gray-700 transition-all active:scale-90 font-bold text-sm tracking-wide gap-2">
                    <i class="fas fa-map-marked-alt text-amber-500"></i>
                    Mapa Geral
                </a>
                <a href="{{ route('rotas.create') }}"
                    class="h-12 px-6 flex items-center justify-center rounded-full bg-white border border-gray-200 shadow-sm hover:bg-gray-50 text-gray-700 transition-all active:scale-90 font-bold text-sm tracking-wide gap-2">
                    <i class="fas fa-plus text-primary"></i>
                    Gerar Roteiro
                </a>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-6 w-full min-w-0 max-w-full" @click.outside="mostrarConfigColunas = false">
            <div x-ref="stickyFilterBar"
                class="rotas-sticky-toolbar sticky top-0 z-20 bg-white rounded-t-2xl transition-all duration-300 space-y-2 mb-2">
                <div class="flex flex-col lg:flex-row items-center justify-between gap-4 w-full">
                    <!-- Canto Esquerdo: Filtros Aplicados -->
                    <div class="flex flex-wrap items-center gap-2">
                        @php
                            $ativos = collect(request()->all())->filter(fn($v, $k) => $v && !in_array($k, ['page', 'sort', 'direction', 'sort_by', 'sort_dir']));
                        @endphp
                        @if ($ativos->count() > 0)
                            <div class="flex flex-wrap items-center gap-1">
                                @foreach ($ativos as $key => $value)
                                    <div
                                        class="flex items-center gap-2 px-3 py-1.5 bg-primary/5 border border-primary/20 rounded-full text-primary group transition-all hover:bg-primary/10">
                                        <span
                                            class="text-[10px] font-black uppercase tracking-widest opacity-50">{{ str_replace('_', ' ', $key) }}</span>
                                        <span class="text-[11px] font-bold">{{ $value }}</span>
                                        <a href="{{ request()->fullUrlWithQuery([$key => null]) }}"
                                            class="hover:text-red-500 transition-colors">
                                            <i class="fas fa-times text-[10px]"></i>
                                        </a>
                                    </div>
                                @endforeach
                                <a href="{{ route('rotas.index') }}"
                                    class="text-[10px] font-black text-gray-400 hover:text-red-500 uppercase tracking-widest ml-2 transition-colors">
                                    Limpar Tudo
                                </a>
                            </div>
                        @endif
                    </div>

                    <!-- Canto Direito: Botões de Configuração -->
                    <div class="flex items-center gap-3 ml-auto">
                        <!-- Botão Toggle Filtros Coluna -->
                        <button @click="mostrarFiltrosColuna = !mostrarFiltrosColuna"
                            class="h-12 w-12 flex items-center justify-center rounded-full bg-white border border-gray-200 shadow-sm hover:bg-gray-50 text-gray-500 transition-all active:scale-90"
                            :class="{ 'border-primary/30 bg-primary/5 text-primary': mostrarFiltrosColuna }"
                            title="Exibir/Ocultar Filtros de Coluna">
                            <i class="fas fa-filter text-lg transition-colors"
                                :class="{ 'text-primary': mostrarFiltrosColuna }"></i>
                        </button>

                        <!-- Botão Colunas -->
                        <div class="relative" @click.outside="mostrarConfigColunas = false">
                            <button @click="mostrarConfigColunas = !mostrarConfigColunas"
                                class="h-12 w-12 flex items-center justify-center rounded-full bg-white border border-gray-200 shadow-sm hover:bg-gray-50 text-gray-500 transition-all active:scale-90"
                                :class="{ 'border-primary/30 bg-primary/5 text-primary': mostrarConfigColunas }"
                                title="Configurar Colunas">
                                <i class="fas fa-columns text-lg"></i>
                            </button>

                            <!-- Dropdown de Colunas -->
                            <div x-show="mostrarConfigColunas" x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                class="absolute right-0 top-full mt-2 w-72 bg-white rounded-[2rem] shadow-2xl border border-gray-100 z-50 overflow-hidden p-2"
                                style="display:none;" x-cloak>
                                <div class="p-4 border-b border-gray-50 flex items-center justify-between">
                                    <h3 class="font-black text-gray-900 text-[11px] uppercase tracking-[0.2em]">Colunas</h3>
                                    <button @click="resetColumns()"
                                        class="text-[10px] font-black text-primary uppercase tracking-widest hover:underline">Restaurar</button>
                                </div>
                                <div class="max-h-80 overflow-y-auto p-2 space-y-1">
                                    <template x-for="col in columns" :key="col.key">
                                        <div
                                            class="flex items-center px-4 py-3 hover:bg-gray-50 rounded-2xl transition-colors cursor-pointer group">
                                            <label class="flex-1 flex items-center gap-3 cursor-pointer">
                                                <input type="checkbox" x-model="col.visible" @change="saveColumnPrefs()"
                                                    class="rounded-lg border-gray-200 text-primary focus:ring-primary h-5 w-5 transition-all">
                                                <span class="text-sm font-semibold text-gray-700" x-text="col.label"></span>
                                            </label>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-full min-w-0">
                <!-- Tabela -->
                <div x-ref="tablePanel" class="table-scroll-panel rotas-table-panel border border-gray-100 rounded-2xl mt-0 shadow-sm">
                        <form method="GET" action="{{ route('rotas.index') }}" id="filtrosInlineForm">
                            @foreach (request()->except(['page', 'sort', 'direction', 'search', 'reset', 'numero_roteiro', 'tecnico', 'operador', 'data_visita', 'data_inicio', 'data_fim', 'qtd_os']) as $key => $value)
                                @if ($value)
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            @if (request('search'))
                                <input type="hidden" name="search" value="{{ request('search') }}">
                            @endif

                            <table class="rotas-table w-full text-xs text-left m-0">
                                <colgroup>
                                    <template x-for="col in columns.filter(c => c.visible)" :key="'col-width-' + col.key">
                                        <col :class="columnClass(col.key)">
                                    </template>
                                    <col class="col-acoes">
                                </colgroup>
                                <thead class="bg-gray-100 uppercase tracking-wider text-[10px] sticky z-10 transition-all"
                                    :style="'top: ' + tableHeaderTop + 'px'">
                                    <tr x-ref="tableHeaderTitleRow">
                                        <template x-for="col in columns.filter(c => c.visible)" :key="col.key">
                                            <th scope="col" :data-col-key="col.key"
                                                class="px-3 py-3 text-left font-black text-black border-b border-gray-200/50"
                                                :class="{
                                                    'col-numero': col.key === 'numero_roteiro',
                                                    'col-data': col.key === 'data_emissao',
                                                    'col-tecnico': col.key === 'tecnico',
                                                    'col-visita': col.key === 'dia_visita',
                                                    'col-operador': col.key === 'operador',
                                                    'col-qtd': col.key === 'qtd_os'
                                                }">
                                                <div class="flex items-center gap-1 group cursor-pointer"
                                                    @click="colunaOrdenavel(col.key) && toggleSortColumn(col.key)">
                                                    <span x-text="col.label"></span>
                                                    <template x-if="getSortBy() === col.key">
                                                        <i class="fas ml-1.5 text-primary"
                                                            :class="getSortDir() === 'asc' ? 'fa-sort-up' : 'fa-sort-down'"></i>
                                                    </template>
                                                    <template x-if="getSortBy() !== col.key">
                                                        <i class="fas fa-sort ml-1.5 opacity-20 group-hover:opacity-100"></i>
                                                    </template>
                                                </div>
                                            </th>
                                        </template>
                                        <th scope="col"
                                            class="col-acoes px-3 py-3 text-center font-black text-black border-b border-gray-200/50">
                                            AÇÕES</th>
                                    </tr>

                                    <!-- Linha de Filtros por Coluna -->
                                    <tr x-transition x-cloak class="filter-row-highlight" x-show="mostrarFiltrosColuna"
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0 -translate-y-2"
                                        x-transition:enter-end="opacity-100 translate-y-0">
                                        <template x-for="col in columns.filter(c => c.visible)" :key="'filter-' + col.key">
                                            <th class="px-1 py-1 border-b border-gray-200/50"
                                                :class="{
                                                    'col-numero': col.key === 'numero_roteiro',
                                                    'col-data': col.key === 'data_emissao',
                                                    'col-tecnico': col.key === 'tecnico',
                                                    'col-visita': col.key === 'dia_visita',
                                                    'col-operador': col.key === 'operador',
                                                    'col-qtd': col.key === 'qtd_os'
                                                }">

                                                <template x-if="col.key === 'numero_roteiro'">
                                                    <input type="search" name="numero_roteiro"
                                                        @keyup.enter="$el.form.submit()" :value="getParam('numero_roteiro')"
                                                        class="w-full px-3 py-2 bg-white/90 border border-black/5 rounded-xl text-[11px] font-bold text-gray-700 focus:bg-white focus:border-primary/30 focus:ring-4 focus:ring-primary/5 transition-all">
                                                </template>

                                                <template x-if="col.key === 'tecnico'">
                                                    <div class="relative">
                                                        <select name="tecnico" @change="$el.form.submit()"
                                                            class="w-full px-3 py-2 bg-white/90 border border-black/5 rounded-xl text-[11px] font-bold text-gray-700 focus:bg-white focus:border-primary/30 focus:ring-4 focus:ring-primary/5 transition-all appearance-none">
                                                            <option value="">TÉCNICO</option>
                                                            @foreach ($tecnicosList as $t)
                                                                <option value="{{ $t }}" {{ request('tecnico') == $t ? 'selected' : '' }}>
                                                                    {{ $t }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <i
                                                            class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 text-[10px] pointer-events-none"></i>
                                                    </div>
                                                </template>

                                                <template x-if="col.key === 'operador'">
                                                    <div class="relative">
                                                        <select name="operador" @change="$el.form.submit()"
                                                            class="w-full px-3 py-2 bg-white/90 border border-black/5 rounded-xl text-[11px] font-bold text-gray-700 focus:bg-white focus:border-primary/30 focus:ring-4 focus:ring-primary/5 transition-all appearance-none">
                                                            <option value="">OPERADOR</option>
                                                            @foreach ($operadoresList as $o)
                                                                <option value="{{ $o }}" {{ request('operador') == $o ? 'selected' : '' }}>
                                                                    {{ $o }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <i
                                                            class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 text-[10px] pointer-events-none"></i>
                                                    </div>
                                                </template>

                                                <template x-if="col.key === 'data_emissao'">
                                                    <input type="date" name="data_inicio" @change="$el.form.submit()"
                                                        :value="getParam('data_inicio')"
                                                        class="w-full px-3 py-2 bg-white/90 border border-black/5 rounded-xl text-[11px] font-bold text-gray-700 focus:bg-white focus:border-primary/30 focus:ring-4 focus:ring-primary/5 transition-all">
                                                </template>

                                                <template x-if="col.key === 'dia_visita'">
                                                    <input type="date" name="data_visita" @change="$el.form.submit()"
                                                        :value="getParam('data_visita')"
                                                        class="w-full px-3 py-2 bg-white/90 border border-black/5 rounded-xl text-[11px] font-bold text-gray-700 focus:bg-white focus:border-primary/30 focus:ring-4 focus:ring-primary/5 transition-all">
                                                </template>

                                                <template x-if="col.key === 'qtd_os'">
                                                    <input type="number" name="qtd_os"
                                                        @keyup.enter="$el.form.submit()" :value="getParam('qtd_os')"
                                                        class="w-full px-3 py-2 bg-white/90 border border-black/5 rounded-xl text-[11px] font-bold text-gray-700 focus:bg-white focus:border-primary/30 focus:ring-4 focus:ring-primary/5 transition-all text-center">
                                                </template>
                                            </th>
                                        </template>
                                        <th class="col-acoes px-1 py-1 border-b border-gray-200/50 text-center">
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template x-for="(row, index) in rows" :key="row.numero_roteiro">
                                        <tr class="hover:bg-gray-50 cursor-pointer transition-colors"
                                            :class="index % 2 === 0 ? 'bg-gray-50/50' : 'bg-white'">
                                            <template x-for="col in columns.filter(c => c.visible)" :key="col.key">
                                                <td class="px-3 py-4">
                                                    <template x-if="col.key === 'numero_roteiro'">
                                                        <span class="text-sm font-bold text-gray-900"
                                                            x-text="row.numero_roteiro"></span>
                                                    </template>
                                                    <template x-if="col.key === 'tecnico'">
                                                        <span class="text-sm font-bold text-gray-900"
                                                            x-text="row.tecnico"></span>
                                                    </template>
                                                    <template x-if="col.key === 'data_emissao'">
                                                        <span class="text-xs font-black text-gray-900"
                                                            x-text="row.data_emissao"></span>
                                                    </template>
                                                    <template x-if="col.key === 'dia_visita'">
                                                        <div class="flex items-center gap-2">
                                                            <i class="far fa-calendar-alt text-gray-300"></i>
                                                            <span class="text-xs font-black text-gray-900"
                                                                x-text="row.dia_visita"></span>
                                                        </div>
                                                    </template>
                                                    <template x-if="col.key === 'operador'">
                                                        <span class="text-xs font-bold text-gray-700" x-text="row.operador"></span>
                                                    </template>
                                                    <template x-if="col.key === 'qtd_os'">
                                                        <span
                                                            class="px-3 py-1 bg-gray-100 text-gray-600 rounded-lg text-xs font-bold"
                                                            x-text="row.qtd_os + ' OS'"></span>
                                                    </template>
                                                </td>
                                            </template>
                                            <td class="px-3 py-4 whitespace-nowrap text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <a :href="row.edit_url"
                                                        class="w-10 h-10 rounded-xl bg-gray-50 text-gray-400 flex items-center justify-center hover:bg-amber-50 hover:text-amber-600 transition-all border border-transparent hover:border-amber-100 shadow-sm"
                                                        title="Editar">
                                                        <i class="fas fa-edit text-sm"></i>
                                                    </a>
                                                    <a :href="row.print_url" target="_blank"
                                                        class="w-10 h-10 rounded-xl bg-gray-50 text-gray-400 flex items-center justify-center hover:bg-primary/10 hover:text-primary transition-all border border-transparent hover:border-primary/20 shadow-sm"
                                                        title="Imprimir PDF">
                                                        <i class="fas fa-file-pdf text-sm"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </form>
                    </div>

                    @if ($roteiros->total() == 0)
                        <div class="flex flex-col items-center justify-center py-20">
                            <div class="w-16 h-16 rounded-full bg-gray-50 flex items-center justify-center mb-4">
                                <i class="fas fa-route text-gray-300 text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 tracking-tight">Nenhum roteiro encontrado</h3>
                            <p class="text-sm text-gray-500 mt-1">Tente ajustar seus filtros de busca.</p>
                        </div>
                    @endif

                    <div class="mt-6 flex flex-col md:flex-row items-center justify-between border-t border-gray-50 pt-6">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">
                            Mostrando {{ $roteiros->count() }} de {{ $roteiros->total() }} registros
                        </p>
                        <div>
                            {{ $roteiros->appends(request()->query())->links('vendor.pagination.tailwind') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
@endsection

        @push('scripts')
            <script>
                function rotasTable(initialData) {
                    return {
                        rows: initialData,
                        search: '{{ request('search') }}',
                        mostrarFiltrosAvancados: false,
                        mostrarConfigColunas: false,
                        mostrarFiltrosColuna: localStorage.getItem('rotas_show_inline_filters') !== 'false',
                        headerHeight: 0,
                        tableHeaderTop: 0,
                        columns: [
                            { key: 'numero_roteiro', label: 'Nº Roteiro', visible: true },
                            { key: 'data_emissao', label: 'Data Emissão', visible: true },
                            { key: 'tecnico', label: 'Técnico', visible: true },
                            { key: 'dia_visita', label: 'Dia da Visita', visible: true },
                            { key: 'operador', label: 'Operador', visible: true },
                            { key: 'qtd_os', label: 'Qtd. OS', visible: true }
                        ],
                        sortableColumns: ['numero_roteiro', 'data_emissao', 'tecnico', 'dia_visita', 'operador', 'qtd_os'],

                        columnClass(key) {
                            return {
                                numero_roteiro: 'col-numero',
                                data_emissao: 'col-data',
                                tecnico: 'col-tecnico',
                                dia_visita: 'col-visita',
                                operador: 'col-operador',
                                qtd_os: 'col-qtd',
                            }[key] || '';
                        },

                        init() {
                            const saved = localStorage.getItem('rotas_columns_v2');
                            if (saved) {
                                const prefs = JSON.parse(saved);
                                this.columns.forEach(col => {
                                    if (prefs[col.key] !== undefined) {
                                        col.visible = prefs[col.key];
                                    }
                                });
                            }

                            this.updateStickyOffsets();
                            window.addEventListener('resize', () => this.updateStickyOffsets());
                            window.addEventListener('scroll', () => this.updateStickyOffsets(), { passive: true });
                            this.$watch('mostrarFiltrosColuna', (value) => {
                                localStorage.setItem('rotas_show_inline_filters', value);
                                this.updateStickyOffsets();
                            });
                        },

                        getSortBy() {
                            const params = new URLSearchParams(window.location.search);
                            return params.get('sort_by') || '';
                        },

                        getSortDir() {
                            const params = new URLSearchParams(window.location.search);
                            return params.get('sort_dir') || '';
                        },

                        colunaOrdenavel(key) {
                            return this.sortableColumns.includes(key);
                        },

                        getSortDirForKey(key) {
                            return this.getSortBy() === key ? this.getSortDir() : '';
                        },

                        getSortIconClass(key) {
                            const dir = this.getSortDirForKey(key);

                            if (dir === 'asc') {
                                return 'fas fa-sort-up';
                            }

                            if (dir === 'desc') {
                                return 'fas fa-sort-down';
                            }

                            return 'fas fa-sort';
                        },

                        sortIconButtonClass(key) {
                            return this.getSortDirForKey(key)
                                ? 'text-primary hover:text-primary/80'
                                : 'text-gray-300 hover:text-gray-500';
                        },

                        sortTitle(key) {
                            const dir = this.getSortDirForKey(key);

                            if (dir === 'asc') {
                                return 'Ordenado crescente. Clique para decrescente';
                            }

                            if (dir === 'desc') {
                                return 'Ordenado decrescente. Clique para remover ordenação';
                            }

                            return 'Clique para ordenar crescente';
                        },

                        toggleSortColumn(key) {
                            if (!this.colunaOrdenavel(key)) return;

                            const currentSortBy = this.getSortBy();
                            const currentDir = this.getSortDirForKey(key);

                            let nextSortBy = key;
                            let nextDir = 'asc';

                            if (currentSortBy === key) {
                                if (currentDir === 'asc') {
                                    nextDir = 'desc';
                                } else if (currentDir === 'desc') {
                                    nextSortBy = null;
                                    nextDir = null;
                                }
                            }

                            this.sortColumn(nextSortBy, nextDir);
                        },

                        sortColumn(key, dir) {
                            const params = new URLSearchParams(window.location.search);
                            params.delete('page');

                            if (key && dir) {
                                params.set('sort_by', key);
                                params.set('sort_dir', dir);
                            } else {
                                params.delete('sort_by');
                                params.delete('sort_dir');
                            }

                            const query = params.toString();
                            window.location.href = query
                                ? window.location.pathname + '?' + query
                                : window.location.pathname;
                        },

                        updateHeaderHeight() {
                            this.$nextTick(() => {
                                if (this.$refs.stickyFilterBar) {
                                    this.headerHeight = this.$refs.stickyFilterBar.offsetHeight;
                                }
                            });
                        },

                        updateStickyOffsets() {
                            this.$nextTick(() => {
                                this.headerHeight = this.$refs.stickyFilterBar ? this.$refs.stickyFilterBar.offsetHeight : 0;

                                if (!this.$refs.tablePanel) {
                                    this.tableHeaderTop = 0;
                                    return;
                                }

                                const tableTop = this.$refs.tablePanel.getBoundingClientRect().top;
                                this.tableHeaderTop = tableTop <= this.headerHeight ? this.headerHeight : 0;
                            });
                        },

                        saveColumnPrefs() {
                            const prefs = {};
                            this.columns.forEach(col => {
                                prefs[col.key] = col.visible;
                            });
                            localStorage.setItem('rotas_columns_v2', JSON.stringify(prefs));
                        },

                        resetColumns() {
                            this.columns.forEach(col => col.visible = true);
                            this.saveColumnPrefs();
                        },

                        refreshPage() {
                            let url = new URL(window.location.href);
                            url.searchParams.set('search', this.search);
                            url.searchParams.delete('page');
                            window.location.href = url.toString();
                        },

                        getParam(name) {
                            const urlParams = new URLSearchParams(window.location.search);
                            return urlParams.get(name) || '';
                        }
                    }
                }
            </script>
        @endpush
