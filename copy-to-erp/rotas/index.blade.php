@extends('layouts.app')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header with Filters -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div>
                    <a href="{{ route('rotas.index') }}"
                        class="inline-flex items-center gap-2 text-xs font-bold text-gray-400 hover:text-red-600 mb-2 uppercase tracking-wide transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z"
                                clip-rule="evenodd" />
                        </svg>
                        Voltar para Painel
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900">
                        @if(isset($roteiro))
                            Editando Roteiro #{{ $roteiro->{'Numero roteiro'} }}
                        @else
                            Planejamento de Rotas
                        @endif
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Organize atendimentos e otimize deslocamentos</p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <!-- Select Técnico -->
                    <div class="flex-1 min-w-[180px]">
                        <label
                            class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1 ml-1">Técnico</label>
                        <select id="filter-technician"
                            class="w-full bg-gray-50 border-none rounded-xl py-2 px-4 text-xs font-bold focus:ring-2 focus:ring-red-500/20 transition-all cursor-pointer">
                            <option value="">Selecione o Técnico</option>
                            @foreach($tecnicos as $tecnico)
                                <option value="{{ $tecnico->codigo }}" @selected(isset($tecnicoCodigo) && $tecnicoCodigo == $tecnico->codigo)>{{ $tecnico->nome }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Select Carro -->
                    <div class="flex-1 min-w-[180px]">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1 ml-1">
                            Carro
                        </label>

                        <select id="filter-car" required
                            class="w-full bg-gray-50 border-none rounded-xl py-2 px-4 text-xs font-bold focus:ring-2 focus:ring-red-500/20 transition-all cursor-pointer">
                            <option value="">Selecione o Carro</option>

                            @foreach($carros as $carro)
                                <option value="{{ $carro->id }}" @selected(isset($carroSelecionado) && $carroSelecionado && $carroSelecionado->id === $carro->id)>
                                    {{ $carro->apelido }}{{ $carro->placa ? ' - ' . $carro->placa : '' }}{{ !$carro->placa && $carro->modelo ? ' - ' . $carro->modelo : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Select Dia (Calendar) -->
                    <div class="flex-1 min-w-[140px]">
                        <label
                            class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1 ml-1">Dia</label>
                        <input type="text" id="filter-day" placeholder="Selecione o Dia"
                            class="w-full bg-gray-50 border-none rounded-xl py-2 px-4 text-xs font-bold focus:ring-2 focus:ring-red-500/20 transition-all cursor-pointer"
                            readonly />
                    </div>
                </div>
            </div>

            <!-- Acompanhantes Section -->
            @php
                $hasAjudantes = isset($ajudantesDefinidos) && collect($ajudantesDefinidos)->filter()->isNotEmpty();
            @endphp
            <div x-data="{ mostrarAjudantes: {{ $hasAjudantes ? 'true' : 'false' }} }"
                x-init="window.addEventListener('expand-helpers', () => mostrarAjudantes = true)" class="w-full mt-4">
                <div class="flex justify-end">
                    <button type="button" @click="mostrarAjudantes = !mostrarAjudantes"
                        class="flex items-center gap-2 text-xs font-bold text-gray-400 hover:text-red-600 transition-all focus:outline-none uppercase tracking-wider group">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 transform transition-transform duration-300"
                            :class="{ 'rotate-180': mostrarAjudantes }" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                        <span>Acompanhantes</span>
                        @if($hasAjudantes)
                            <span class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-pulse"></span>
                        @endif
                    </button>
                </div>

                <div x-show="mostrarAjudantes" x-collapse x-cloak
                    class="mt-4 pt-4 border-t border-gray-50 dark:border-gray-800/50">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        @for($i = 1; $i <= 4; $i++)
                            <div class="flex flex-col">
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 ml-1">Ajudante
                                    {{ $i }}</label>
                                <select id="filter-ajudante{{ $i }}"
                                    class="w-full bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700 rounded-xl py-2.5 px-4 text-xs font-bold text-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-red-500/20 focus:bg-white dark:focus:bg-gray-900 transition-all shadow-sm cursor-pointer">
                                    <option value="">Selecione</option>
                                    @foreach($tecnicos as $tecnico)
                                        <option value="{{ $tecnico->nome }}" @selected(isset($ajudantesDefinidos['ajudante' . $i]) && $ajudantesDefinidos['ajudante' . $i] == $tecnico->nome)>
                                            {{ $tecnico->nome }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            <!-- Left Column: Latest OS (3/12) -->
            <div class="lg:col-span-3 space-y-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-gray-800">Últimas OS</h2>
                        <div class="flex items-center gap-1">
                            <button id="prev-os-page"
                                class="p-1 text-gray-400 hover:text-red-600 transition-colors disabled:opacity-30" disabled>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <span id="os-page-info" class="text-[10px] font-bold text-gray-500">1/1</span>
                            <button id="next-os-page"
                                class="p-1 text-gray-400 hover:text-red-600 transition-colors disabled:opacity-30" disabled>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- OS Filters -->
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        <input type="text" id="os-search-client" placeholder="Cliente..."
                            class="w-full px-2 py-1.5 bg-gray-50 border border-gray-100 rounded-lg text-[10px] focus:ring-1 focus:ring-red-500/20 outline-none transition-all" />
                        <input type="text" id="os-search-number" placeholder="Nº OS..."
                            class="w-full px-2 py-1.5 bg-gray-50 border border-gray-100 rounded-lg text-[10px] focus:ring-1 focus:ring-red-500/20 outline-none transition-all" />
                    </div>
                    <div class="mb-4">
                        <select id="os-search-day"
                            class="w-full px-2 py-1.5 bg-gray-50 border border-gray-100 rounded-lg text-[10px] focus:ring-1 focus:ring-red-500/20 outline-none transition-all">
                            <option value="">Todos os dias</option>
                            <option value="segunda">Segunda - 2º</option>
                            <option value="terca">Terça - 3º</option>
                            <option value="quarta">Quarta - 4º</option>
                            <option value="quinta">Quinta - 5º</option>
                            <option value="sexta">Sexta - 6º</option>
                            <option value="sabado">Sábado</option>
                            <option value="domingo">Domingo</option>
                        </select>
                    </div>

                    <div id="latest-os-list" class="space-y-2 max-h-[600px] overflow-y-auto pr-1 custom-scrollbar">
                        <div class="flex items-center justify-center py-8">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-red-600"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Middle Column: Points List (4/12) -->
            <div class="lg:col-span-4 space-y-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-bold text-gray-800">Pontos da Rota</h2>
                        <span id="points-count-badge"
                            class="bg-red-100 text-red-600 text-[10px] font-bold px-2 py-0.5 rounded-full">0</span>
                    </div>
                    <div id="points-list" class="space-y-2 max-h-[600px] overflow-y-auto pr-1 custom-scrollbar">
                        <p class="text-gray-400 text-xs text-center py-8">Nenhum ponto adicionado</p>
                    </div>
                </div>
            </div>

            <!-- Right Column: Map & Controls (5/12) -->
            <div class="lg:col-span-5 space-y-4">
                <!-- Map -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div id="map" class="w-full h-[350px]"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Add Point Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                        <form id="add-address-form" class="space-y-3">
                            <!-- Unified Address/CEP Input -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div class="md:col-span-2">
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Endereço ou
                                        CEP</label>
                                    <input type="text" id="address-input" placeholder="Rua, Bairro, Cidade ou 00000-000"
                                        class="w-full px-3 py-2 bg-gray-50 border-none rounded-lg text-xs focus:ring-2 focus:ring-red-500/20 transition-all" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Número</label>
                                    <input type="text" id="numero-input" placeholder="Nº"
                                        class="w-full px-3 py-2 bg-gray-50 border-none rounded-lg text-xs focus:ring-2 focus:ring-red-500/20 transition-all" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Horas</label>
                                    <input type="number" id="service-hours-input" value="1" min="0"
                                        class="w-full px-3 py-2 bg-gray-50 border-none rounded-lg text-xs focus:ring-2 focus:ring-red-500/20 transition-all" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Minutos</label>
                                    <input type="number" id="service-minutes-input" value="0" min="0" max="59"
                                        class="w-full px-3 py-2 bg-gray-50 border-none rounded-lg text-xs focus:ring-2 focus:ring-red-500/20 transition-all" />
                                </div>
                            </div>

                            <button type="submit"
                                class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded-lg text-xs transition-all flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                Adicionar Ponto
                            </button>
                        </form>
                    </div>

                    <!-- Summary Card -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                        <h2 class="text-sm font-bold text-gray-800 mb-3">Resumo da Rota</h2>
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <div class="p-2 bg-red-50 rounded-lg">
                                <p class="text-[10px] text-red-600 font-bold uppercase">Distância</p>
                                <p id="total-distance" class="text-sm font-bold text-gray-800">0 km</p>
                            </div>
                            <div class="p-2 bg-emerald-50 rounded-lg">
                                <p class="text-[10px] text-emerald-600 font-bold uppercase">Duração</p>
                                <p id="total-duration" class="text-sm font-bold text-gray-800">0 min</p>
                            </div>
                            <div class="p-2 bg-orange-50 rounded-lg">
                                <p class="text-[10px] text-orange-600 font-bold uppercase">Serviço</p>
                                <p id="total-service-time" class="text-sm font-bold text-gray-800">0 min</p>
                            </div>
                            <div class="p-2 bg-rose-50 rounded-lg">
                                <p class="text-[10px] text-rose-600 font-bold uppercase">Retorno</p>
                                <p id="return-time" class="text-sm font-bold text-gray-800">--:--</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button id="open-google-maps"
                                class="hidden flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 rounded-lg text-xs transition-all flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                                </svg>
                                Maps
                            </button>
                            <button id="clear-route-btn"
                                class="flex-1 bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold py-2 rounded-lg text-xs transition-all flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Limpar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <div class="fixed bottom-8 right-8 z-[1000]">
        <button id="generate-script-btn"
            class="flex items-center gap-3 bg-red-600 hover:bg-red-700 text-white px-8 py-4 rounded-full shadow-2xl shadow-red-500/40 transition-all hover:scale-105 active:scale-95 font-bold text-lg group">
            <svg class="w-6 h-6 transition-transform group-hover:rotate-12" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <span>Gerar roteiro</span>
        </button>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl px-6 py-5 shadow-2xl w-[min(24rem,calc(100%-2rem))] flex items-center gap-4">
            <svg class="animate-spin h-8 w-8 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
            </svg>
            <div class="min-w-0">
                <p id="loading-message" class="text-sm font-bold text-gray-800">Processando...</p>
                <p id="loading-detail" class="hidden mt-1 text-xs text-gray-500"></p>
            </div>
        </div>
    </div>

    </div>


    @push('scripts')
        <!-- Flatpickr CSS & JS for Calendar -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>
        <!-- Leaflet CSS -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <!-- SortableJS for drag and drop -->
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
        <!-- Leaflet JS -->
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

        <script>
            // Disable auto-reload for this page
            window.preventReload = true;


            // Default locations
            const DEFAULT_START = {
                name: 'Tecnoar Galpão',
                cep: '02116-000',
                isDefault: true,
                isStart: true
            };

            const DEFAULT_END = {
                name: 'Tecnoar Matriz',
                cep: '02125-001',
                isDefault: true,
                isEnd: true
            };

            // Route Planning Application
            class RoutePlanner {
                constructor() {
                    this.map = null;
                    this.points = [];
                    this.markers = [];
                    this.routeLine = null;
                    this.sortable = null;
                    this.currentTechnician = '';
                    this.currentCar = '';
                    this.tecnicos = @json($tecnicos ?? []);
                    this.osPage = 1;
                    this.osLastPage = 1;
                    this.numeroRoteiro = @json($roteiro->{'Numero roteiro'} ?? null);
                    this.pointIdCounter = 0;
                    this.geocodeCache = new Map();
                    this.latestOsAbortController = null;
                    this.init();
                }

                async init() {
                    const isEditingRoute = Boolean(this.numeroRoteiro);

                    this.initMap();

                    if (this.numeroRoteiro) {
                        // Clear storage when editing an existing route to prevent doubling OSs
                        localStorage.removeItem('route_planner_points');
                        this.points = [];
                    } else {
                        this.loadFromStorage();
                    }

                    // Always ensure default points are loaded if empty
                    if (this.points.length === 0) {
                        await this.loadDefaultPoints();
                    }

                    // Initialize Flatpickr for day selection
                    this.initDatePicker();

                    // Initialize technician from select if already set
                    const techSelect = document.getElementById('filter-technician');
                    const carSelect = document.getElementById('filter-car');

                    @if(isset($roteiro))
                        // Pre-fill from roteiro
                        const tecnicoNome = @json($roteiro->Tecnico);
                        const diaVisita = @json($roteiro->itens->first()->{'Data visita'} ?? null);

                        // Find technician by name if possible, or just set text
                        for (let i = 0; i < techSelect.options.length; i++) {
                            if (techSelect.options[i].text === tecnicoNome) {
                                techSelect.selectedIndex = i;
                                break;
                            }
                        }
                        this.currentTechnician = tecnicoNome;

                        // Initialize car from backend if exists
                        @if(isset($carroSelecionado) && $carroSelecionado)
                            const carroSelecionadoId = @json($carroSelecionado->id);

                            if (carSelect) {
                                for (let i = 0; i < carSelect.options.length; i++) {
                                    if (String(carSelect.options[i].value) === String(carroSelecionadoId)) {
                                        carSelect.selectedIndex = i;
                                        break;
                                    }
                                }
                            }

                            // guarda estado interno se existir lógica dependente
                            this.currentCar = carroSelecionadoId;
                        @endif

                        if (diaVisita) {
                            // diaVisita is likely 'YYYY-MM-DD HH:MM:SS' or 'YYYY-MM-DD'
                            const formattedDate = diaVisita.split(' ')[0];

                            // Set the date in Flatpickr
                            if (this.datePicker) {
                                this.datePicker.setDate(formattedDate, true);
                            }
                        }
                    @endif

                    if (techSelect.value !== '' && !this.currentTechnician) {
                        this.currentTechnician = techSelect.options[techSelect.selectedIndex].text;
                    }

                    this.attachEventListeners();
                    this.initSortable();
                    this.updateUI();
                    void this.fetchLatestOs();

                    // Manual pin on map click
                    this.map.on('click', (e) => {
                        this.handleMapClick(e.latlng);
                    });

                    if (isEditingRoute) {
                        this.showLoading(true, {
                            message: `Carregando roteiro #${this.numeroRoteiro}...`,
                            detail: 'Preparando os pontos da rota'
                        });
                    }

                    try {
                        @if(isset($roteiro))
                            await this.loadExistingRoutePoints(@json($roteiro->itens ?? []));
                        @endif

                        const preSelectedOs = @json($preSelectedOs ?? []);
                        if (preSelectedOs.length > 0) {
                            this.showLoading(true, { message: 'Carregando seleção do mapa...' });
                            await this.loadPreSelectedOs(preSelectedOs);
                        }

                        // Initial route calculation if we have points
                        if (this.points.length >= 2) {
                            if (isEditingRoute) {
                                this.showLoading(true, {
                                    message: `Carregando roteiro #${this.numeroRoteiro}...`,
                                    detail: 'Calculando o trajeto no mapa'
                                });
                            }

                            await this.calculateRoute({
                                showLoading: !isEditingRoute,
                                notifyOnError: false
                            });
                        }
                    } finally {
                        if (isEditingRoute) {
                            this.showLoading(false);
                        }
                    }
                }

                generatePointId() {
                    this.pointIdCounter += 1;
                    return (Date.now() * 1000) + this.pointIdCounter;
                }

                buildClientAddress(cliente) {
                    const addressParts = [];

                    if (cliente) {
                        if (cliente.Endereco) addressParts.push(cliente.Endereco);
                        if (cliente.Numero) addressParts.push(cliente.Numero);
                        if (cliente.Bairro) addressParts.push(cliente.Bairro);
                        if (cliente.Cidade) addressParts.push(cliente.Cidade);
                        if (cliente.Uf) addressParts.push(cliente.Uf);
                    }

                    return addressParts.join(', ');
                }

                normalizeClientName(name = '') {
                    return String(name || '').replace(/ - (CONSUMIDOR|REVENDA|OFICINA|POSTO).*$/i, '');
                }

                extractStreetNumber(address = '') {
                    const match = String(address).match(/,\s*(\d+)/);
                    return match ? match[1] : '1';
                }

                getGeocodeCacheKey({ address = '', cep = '', numero = '1' } = {}) {
                    return [String(cep || '').replace(/\D/g, ''), String(numero || '1').trim(), String(address || '').trim().toLowerCase()]
                        .join('|');
                }

                getCachedGeocode(cacheKey) {
                    if (this.geocodeCache.has(cacheKey)) {
                        return this.geocodeCache.get(cacheKey);
                    }

                    try {
                        const cached = localStorage.getItem(`rotas_geocode_v1:${cacheKey}`);
                        if (!cached) return null;

                        const parsed = JSON.parse(cached);
                        this.geocodeCache.set(cacheKey, parsed);
                        return parsed;
                    } catch (error) {
                        console.warn('[ROTAS] Falha ao ler cache local de geocode:', error);
                        return null;
                    }
                }

                storeGeocodeCache(cacheKey, data) {
                    this.geocodeCache.set(cacheKey, data);

                    try {
                        localStorage.setItem(`rotas_geocode_v1:${cacheKey}`, JSON.stringify(data));
                    } catch (error) {
                        console.warn('[ROTAS] Falha ao salvar cache local de geocode:', error);
                    }

                    return data;
                }

                async resolveGeocode({ address = '', cep = '', numero = '1' } = {}) {
                    const normalizedCep = String(cep || '').replace(/\D/g, '');
                    const resolvedNumber = String(numero || this.extractStreetNumber(address) || '1').trim() || '1';
                    const cacheKey = this.getGeocodeCacheKey({
                        address,
                        cep: normalizedCep,
                        numero: resolvedNumber
                    });
                    const cached = this.getCachedGeocode(cacheKey);

                    if (cached) {
                        return cached;
                    }

                    let geocodeData = null;

                    if (normalizedCep) {
                        const cepResponse = await fetch('{{ route("rotas.geocode-cep") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                cep: normalizedCep,
                                numero: resolvedNumber
                            })
                        });

                        geocodeData = await cepResponse.json();
                    }

                    if ((!geocodeData || !geocodeData.success) && address) {
                        const normalizedAddress = /,\s*Brasil$/i.test(address)
                            ? address
                            : `${address}, Brasil`;

                        const response = await fetch('{{ route("rotas.geocode") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ address: normalizedAddress })
                        });

                        geocodeData = await response.json();
                    }

                    if (geocodeData?.success) {
                        return this.storeGeocodeCache(cacheKey, geocodeData);
                    }

                    return geocodeData;
                }

                createPoint({
                    address,
                    lat,
                    lon,
                    technician = '',
                    osNumber = '',
                    clientName = '',
                    serviceTimeMinutes = 0,
                    alertaHorario = '',
                    helpers = [],
                    isDefault = false,
                    isStart = false,
                    isEnd = false
                }) {
                    return {
                        id: this.generatePointId(),
                        address,
                        lat,
                        lon,
                        technician,
                        osNumber,
                        clientName,
                        serviceTimeMinutes,
                        alertaHorario,
                        helpers,
                        isDefault,
                        isStart,
                        isEnd
                    };
                }

                applyHeaderHelpers(osHelpers = []) {
                    const helperInputs = [1, 2, 3, 4]
                        .map(index => document.getElementById(`filter-ajudante${index}`))
                        .filter(Boolean);
                    const headerHelpers = helperInputs.map(input => input.value).filter(Boolean);

                    if (headerHelpers.length > 0 || osHelpers.length === 0) {
                        return;
                    }

                    osHelpers.forEach((helper, index) => {
                        if (helperInputs[index]) {
                            helperInputs[index].value = helper;
                        }
                    });

                    window.dispatchEvent(new CustomEvent('expand-helpers'));
                }

                async loadExistingRoutePoints(items) {
                    const sortedItems = [...items].sort((a, b) => {
                        const seqA = a['Seq tecnico'] || 0;
                        const seqB = b['Seq tecnico'] || 0;
                        return seqA - seqB;
                    });

                    if (sortedItems.length === 0) {
                        return;
                    }

                    const failedOs = [];
                    let loadedItems = 0;

                    const results = await Promise.allSettled(sortedItems.map(async (item) => {
                        try {
                            const os = item.ordem;
                            if (!os) {
                                return { osNumber: item['No os'], point: null };
                            }

                            const cliente = os.cliente_by_codigo;
                            const endereco = this.buildClientAddress(cliente);
                            const ordemApi = os.ordem_api || {};
                            const tempoEstimado = ordemApi.tempo_estimado || 60;
                            const alertaHorario = ordemApi.alerta_horario || '';
                            const helpers = [ordemApi.ajudante1, ordemApi.ajudante2, ordemApi.ajudante3, ordemApi.ajudante4].filter(Boolean);
                            const geocodeData = await this.resolveGeocode({
                                address: endereco,
                                cep: cliente?.Cep || '',
                                numero: cliente?.Numero || this.extractStreetNumber(endereco)
                            });

                            if (!geocodeData?.success) {
                                return { osNumber: item['No os'], point: null };
                            }

                            return {
                                osNumber: item['No os'],
                                point: this.createPoint({
                                    address: geocodeData.data.display_name || endereco,
                                    lat: geocodeData.data.lat,
                                    lon: geocodeData.data.lon,
                                    technician: this.currentTechnician,
                                    osNumber: item['No os'],
                                    clientName: cliente?.['Nome conhecido'] || '',
                                    serviceTimeMinutes: parseInt(tempoEstimado) || 60,
                                    alertaHorario,
                                    helpers
                                })
                            };
                        } finally {
                            loadedItems += 1;
                            this.showLoading(true, {
                                message: `Carregando roteiro #${this.numeroRoteiro}...`,
                                detail: `${loadedItems}/${sortedItems.length} OS carregadas`
                            });
                        }
                    }));

                    const loadedPoints = [];

                    results.forEach((result, index) => {
                        const osNumber = sortedItems[index]?.['No os'];

                        if (result.status !== 'fulfilled' || !result.value.point) {
                            failedOs.push(result.status === 'fulfilled' ? result.value.osNumber : osNumber);
                            return;
                        }

                        loadedPoints.push(result.value.point);
                    });

                    if (loadedPoints.length > 0) {
                        const endIndex = this.points.findIndex(point => point.isEnd);
                        const insertIndex = endIndex === -1 ? this.points.length : endIndex;

                        this.points.splice(insertIndex, 0, ...loadedPoints);
                        loadedPoints.forEach(point => this.addMarker(point));

                        const firstHelpers = loadedPoints.find(point => point.helpers && point.helpers.length > 0);
                        if (firstHelpers) {
                            this.applyHeaderHelpers(firstHelpers.helpers);
                        }

                        this.saveToStorage();
                        this.updateUI();
                    }

                    if (failedOs.length > 0) {
                        console.warn('[ROTAS] Algumas OS nao puderam ser carregadas automaticamente no roteiro:', failedOs);
                    }
                }

                initMap() {
                    this.map = L.map('map').setView([-23.5505, -46.6333], 12);

                    const lightLayer = L.tileLayer(
                        'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
                        { subdomains: 'abcd', maxZoom: 20, attribution: '© OpenStreetMap contributors © CARTO' }
                    );

                    const darkLayer = L.tileLayer(
                        'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
                        { subdomains: 'abcd', maxZoom: 20, attribution: '© OpenStreetMap contributors © CARTO' }
                    );

                    const THEME_KEY = 'theme'; // 'dark' | 'light'

                    const getStoredTheme = () => {
                        try { return localStorage.getItem(THEME_KEY); } catch { return null; }
                    };
                    const setStoredTheme = (val) => {
                        try { localStorage.setItem(THEME_KEY, val); } catch { }
                    };

                    const hasDarkClass = () =>
                        document.documentElement.classList.contains('dark') ||
                        document.documentElement.classList.contains('dark-mode');

                    const isDark = () => {
                        const stored = getStoredTheme();
                        if (stored === 'dark') return true;
                        if (stored === 'light') return false;

                        return hasDarkClass()
                            || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    };

                    // ... no init:
                    if (isDark()) darkLayer.addTo(this.map);
                    else lightLayer.addTo(this.map);

                    this._tileLayers = { lightLayer, darkLayer, isDark };

                    // Observer: salva e atualiza corretamente
                    this._darkObserver = new MutationObserver(() => {
                        setStoredTheme(hasDarkClass() ? 'dark' : 'light');
                        this.updateMapTheme();
                    });

                    this._darkObserver.observe(document.documentElement, {
                        attributes: true,
                        attributeFilter: ['class']
                    });
                }

                updateMapTheme() {
                    if (!this._tileLayers) return;

                    const { lightLayer, darkLayer, isDark } = this._tileLayers;
                    const dark = isDark();

                    if (this.map.hasLayer(lightLayer)) this.map.removeLayer(lightLayer);
                    if (this.map.hasLayer(darkLayer)) this.map.removeLayer(darkLayer);

                    (dark ? darkLayer : lightLayer).addTo(this.map);
                }

                initDatePicker() {
                    // Initialize Flatpickr on the day input
                    this.datePicker = flatpickr("#filter-day", {
                        locale: "pt",
                        dateFormat: "Y-m-d",
                        altInput: true,
                        altFormat: "d/m/Y (D)",
                        maxDate: new Date().fp_incr(30), // 30 days from today
                        defaultDate: "{{ $dataVisita ?? null }}",
                        allowInput: false
                    });
                }

                async loadDefaultPoints() {
                    const defaultPoints = await Promise.all([
                        this.buildDefaultPoint(DEFAULT_START),
                        this.buildDefaultPoint(DEFAULT_END)
                    ]);

                    defaultPoints
                        .filter(Boolean)
                        .forEach(point => {
                            this.points.push(point);
                            this.addMarker(point);
                        });

                    this.saveToStorage();
                }

                async buildDefaultPoint(defaultPoint) {
                    try {
                        const result = await this.resolveGeocode({
                            cep: defaultPoint.cep,
                            numero: '1',
                            address: defaultPoint.name
                        });

                        if (!result?.success) {
                            return null;
                        }

                        return this.createPoint({
                            address: defaultPoint.name,
                            lat: result.data.lat,
                            lon: result.data.lon,
                            technician: '',
                            osNumber: '',
                            clientName: defaultPoint.name,
                            serviceTimeMinutes: 0,
                            isDefault: defaultPoint.isDefault || false,
                            isStart: defaultPoint.isStart || false,
                            isEnd: defaultPoint.isEnd || false
                        });
                    } catch (error) {
                        console.error('Error adding default point:', error);
                        return null;
                    }
                }

                attachEventListeners() {
                    // Add address form
                    document.getElementById('add-address-form').addEventListener('submit', async (e) => {
                        e.preventDefault();
                        await this.addPoint();
                    });

                    // Clear route button
                    document.getElementById('clear-route-btn').addEventListener('click', () => {
                        this.clearRoute();
                    });

                    // CEP input mask
                    const cepInput = document.getElementById('cep-input');
                    if (cepInput) {
                        cepInput.addEventListener('input', (e) => {
                            let value = e.target.value.replace(/\D/g, '');
                            if (value.length > 5) {
                                value = value.slice(0, 5) + '-' + value.slice(5, 8);
                            }
                            e.target.value = value;
                        });
                    }

                    // Technician filter change - REMOVED technician filter from OS search
                    document.getElementById('filter-technician').addEventListener('change', (e) => {
                        this.currentTechnician = e.target.options[e.target.selectedIndex].text;
                        if (e.target.value === '') this.currentTechnician = '';
                        // No longer fetching OS here as it's not filtered by technician anymore
                    });

                    // OS Pagination
                    document.getElementById('prev-os-page').addEventListener('click', () => {
                        if (this.osPage > 1) {
                            this.osPage--;
                            this.fetchLatestOs();
                        }
                    });

                    document.getElementById('next-os-page').addEventListener('click', () => {
                        if (this.osPage < this.osLastPage) {
                            this.osPage++;
                            this.fetchLatestOs();
                        }
                    });

                    // OS Search Filters
                    let searchTimeout;
                    const handleSearch = () => {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(() => {
                            this.osPage = 1;
                            this.fetchLatestOs();
                        }, 500);
                    };

                    document.getElementById('os-search-client').addEventListener('input', handleSearch);
                    document.getElementById('os-search-number').addEventListener('input', handleSearch);
                    document.getElementById('os-search-day').addEventListener('change', handleSearch);

                    // Open in Google Maps
                    document.getElementById('open-google-maps').addEventListener('click', () => {
                        this.openGoogleMaps();
                    });

                    // Generate Script Button
                    document.getElementById('generate-script-btn').addEventListener('click', () => {
                        this.saveRoute();
                    });

                }

                initSortable() {
                    const pointsList = document.getElementById('points-list');
                    this.sortable = Sortable.create(pointsList, {
                        animation: 150,
                        handle: '.drag-handle',
                        filter: '.no-drag',
                        onEnd: (evt) => {
                            // Reorder points array
                            // Note: We need to account for the fixed start/end points if they are in the list
                            // The list UI shows all points. Start is index 0, End is index length-1.
                            // Only intermediate points should be draggable.

                            const movedPoint = this.points.splice(evt.oldIndex, 1)[0];
                            this.points.splice(evt.newIndex, 0, movedPoint);

                            // Redraw markers and route
                            this.redrawMap();
                            this.saveToStorage();
                            this.updateUI();
                        }
                    });
                }

                async addPoint() {
                    const addressInput = document.getElementById('address-input');
                    const numeroInput = document.getElementById('numero-input');

                    let address = addressInput.value.trim();
                    const numero = numeroInput.value.trim();
                    let geocodeData = null;

                    if (!address) {
                        this.showError('Por favor, insira um endereço ou CEP');
                        return;
                    }

                    this.showLoading(true);

                    try {
                        // Check if input looks like a CEP (8 digits, optional hyphen)
                        const cepMatch = address.replace(/\D/g, '').match(/^(\d{8})$/);

                        if (cepMatch) {
                            // It's a CEP
                            const cep = cepMatch[1];
                            if (!numero) {
                                this.showError('Por favor, insira o número para o CEP');
                                this.showLoading(false);
                                return;
                            }

                            // Geocode by CEP
                            const response = await fetch('{{ route("rotas.geocode-cep") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ cep, numero })
                            });

                            geocodeData = await response.json();
                        } else {
                            // It's an address
                            // Append number if provided
                            if (numero) {
                                address += `, ${numero}`;
                            }

                            // Geocode address
                            const response = await fetch('{{ route("rotas.geocode") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ address })
                            });

                            geocodeData = await response.json();
                        }

                        if (geocodeData?.success) {
                            await this.addPointToRoute(geocodeData.data.lat, geocodeData.data.lon, geocodeData.data.display_name);
                            this.clearForm();
                            this.showSuccess('Ponto adicionado com sucesso!');
                        } else {
                            this.showError(geocodeData.message || 'Não foi possível geocodificar o endereço. Tente ser mais específico (ex: Rua, Número, Cidade).');
                        }
                    } catch (error) {
                        console.error('Error adding point:', error);
                        this.showError('Erro ao adicionar ponto: ' + error.message);
                    } finally {
                        this.showLoading(false);
                    }
                }

                async handleMapClick(latlng) {
                    if (confirm(`Deseja adicionar este local clicado à rota?`)) {
                        await this.addPointToRoute(latlng.lat, latlng.lng, `Ponto no mapa (${latlng.lat.toFixed(4)}, ${latlng.lng.toFixed(4)})`);
                        this.showSuccess('Ponto adicionado pelo mapa!');
                    }
                }

                async addPointToRoute(lat, lon, address, osNumber = '', clientName = '') {
                    // Business rule: max visits (removed limit per request)
                    const osPoints = this.points.filter(p => p.osNumber).length;

                    const technician = this.currentTechnician;
                    const serviceHours = parseInt(document.getElementById('service-hours-input')?.value || '1');
                    const serviceMinutes = parseInt(document.getElementById('service-minutes-input')?.value || '0');

                    const point = {
                        id: Date.now(),
                        address: address,
                        lat: lat,
                        lon: lon,
                        technician,
                        osNumber,
                        clientName,
                        serviceTimeMinutes: (serviceHours * 60) + serviceMinutes,
                        isDefault: false,
                        isStart: false,
                        isEnd: false
                    };

                    if (this.points.length >= 2) {
                        this.points.splice(this.points.length - 1, 0, point);
                    } else {
                        this.points.push(point);
                    }

                    this.addMarker(point);
                    this.saveToStorage();
                    this.updateUI();

                    if (this.points.length >= 2) {
                        await this.calculateRoute();
                    }
                }

                addMarker(point) {
                    // Calcular sequência apenas para pontos de OS (não default)
                    const osPoints = this.points.filter(p => p.osNumber && !p.isDefault);
                    const osIndex = osPoints.indexOf(point);
                    const sequenceNumber = osIndex >= 0 ? osIndex + 1 : null;

                    // Custom icon for start/end points
                    let iconHtml;
                    if (point.isStart) {
                        iconHtml = `<div style="background-color: #10B981; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">🏁</div>`;
                    } else if (point.isEnd) {
                        iconHtml = `<div style="background-color: #EF4444; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">🏁</div>`;
                    } else {
                        iconHtml = `<div style="background-color: #3B82F6; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">${sequenceNumber}</div>`;
                    }

                    const customIcon = L.divIcon({
                        html: iconHtml,
                        className: 'custom-marker',
                        iconSize: [30, 30],
                        iconAnchor: [15, 15]
                    });

                    const marker = L.marker([point.lat, point.lon], {
                        icon: customIcon,
                        title: point.address
                    }).addTo(this.map);

                    // Custom popup content
                    const popupContent = `
                                                                                                                                                                                                                                    <div class="p-2">
<div class="font-bold ${point.isStart ? 'text-green-600' : point.isEnd ? 'text-red-600' : 'text-red-600'} mb-2">
    ${point.isStart ? '🏁 Início' : point.isEnd ? '🏁 Fim' : `Ponto ${sequenceNumber}`}
</div>
 <div class="text-sm mb-1"><strong>Endereço:</strong> ${point.address}</div>
 ${point.technician ? `<div class="text-sm mb-1"><strong>Técnico:</strong> ${point.technician}</div>` : ''}
 ${point.helpers && point.helpers.length > 0 ? `<div class="text-sm mb-1"><strong>Ajudantes:</strong> ${point.helpers.join(', ')}</div>` : ''}
 ${point.osNumber ? `<div class="text-sm mb-1"><strong>OS:</strong> ${point.osNumber}</div>` : ''}
 ${point.serviceTimeMinutes > 0 ? `<div class="text-sm"><strong>Tempo:</strong> ${Math.floor(point.serviceTimeMinutes / 60)}h ${point.serviceTimeMinutes % 60}min</div>` : ''}
                                                                                                                                                                                                                                     </div>
                                                                                                                                                                                                                                `;

                    marker.bindPopup(popupContent);
                    this.markers.push(marker);

                    // Fit map to show all markers
                    if (this.markers.length > 0) {
                        const group = L.featureGroup(this.markers);
                        this.map.fitBounds(group.getBounds().pad(0.1));
                    }
                }

                redrawMap() {
                    // Remove all markers
                    this.markers.forEach(m => this.map.removeLayer(m));
                    this.markers = [];

                    // Redraw markers in new order
                    this.points.forEach(point => this.addMarker(point));

                    // Recalculate route
                    if (this.points.length >= 2) {
                        this.calculateRoute();
                    }
                }

                async calculateRoute(options = {}) {
                    if (this.points.length < 2) return;

                    const {
                        showLoading = true,
                        notifyOnError = true
                    } = options;

                    if (showLoading) {
                        this.showLoading(true);
                    }

                    try {
                        let pointsForRoute = [...this.points];

                        // Calculate final route
                        const response = await fetch('{{ route("rotas.calculate") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                points: pointsForRoute.map(p => ({ lat: p.lat, lon: p.lon }))
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            this.drawRoute(result.data);
                            document.getElementById('open-google-maps').classList.remove('hidden');
                        } else {
                            if (notifyOnError) {
                                this.showError(result.message);
                            }
                        }
                    } catch (error) {
                        console.error('Error calculating route:', error);
                        if (notifyOnError) {
                            this.showError('Erro ao calcular rota. Tente novamente.');
                        }
                    } finally {
                        if (showLoading) {
                            this.showLoading(false);
                        }
                    }
                }

                openGoogleMaps() {
                    if (this.points.length < 2) return;

                    const baseUrl = 'https://www.google.com/maps/dir/';
                    const coordinates = this.points.map(p => `${p.lat},${p.lon}`).join('/');
                    window.open(baseUrl + coordinates, '_blank');
                }

                drawRoute(routeData) {
                    // Remove existing route line
                    if (this.routeLine) {
                        this.map.removeLayer(this.routeLine);
                    }

                    // Convert coordinates from [lon, lat] to [lat, lon] for Leaflet
                    const latLngs = routeData.geometry.map(coord => [coord[1], coord[0]]);

                    // Draw route line
                    this.routeLine = L.polyline(latLngs, {
                        color: '#3B82F6',
                        weight: 4,
                        opacity: 0.7
                    }).addTo(this.map);

                    // Update summary
                    const distanceKm = (routeData.distance / 1000).toFixed(1);
                    const durationMin = Math.round(routeData.duration / 60);
                    const totalServiceTime = this.points.reduce((sum, p) => sum + (p.serviceTimeMinutes || 0), 0);
                    const totalTimeMin = durationMin + totalServiceTime;

                    // Calculate return time (assuming start at 8:00 AM)
                    const startTime = new Date();
                    startTime.setHours(8, 0, 0, 0);
                    const returnTime = new Date(startTime.getTime() + totalTimeMin * 60000);

                    if (document.getElementById('total-distance')) document.getElementById('total-distance').textContent = `${distanceKm} km`;
                    if (document.getElementById('total-duration')) document.getElementById('total-duration').textContent = `${durationMin} min`;
                    if (document.getElementById('total-service-time')) document.getElementById('total-service-time').textContent = `${Math.floor(totalServiceTime / 60)}h ${totalServiceTime % 60}min`;
                    if (document.getElementById('return-time')) document.getElementById('return-time').textContent = `${returnTime.getHours().toString().padStart(2, '0')}:${returnTime.getMinutes().toString().padStart(2, '0')}`;
                }

                removePoint(pointId, force = false) {
                    const point = this.points.find(p => p.id == pointId);

                    // Don't allow removing default start point
                    if (point && point.isStart && !force) {
                        this.showError('Não é possível remover o ponto de partida padrão');
                        return;
                    }

                    // If it's an OS point, ask if they want to close it
                    if (point && point.osNumber && !force) {
                        this.showRemoveOsModal(point);
                    } else {
                        // Not an OS point, or forced (rescheduling), just remove it from view
                        this.points = this.points.filter(p => p.id != pointId);
                        this.redrawMap();
                        this.saveToStorage();
                        this.updateUI();

                        if (!force) {
                            this.showSuccess('Ponto removido com sucesso!');
                        }
                    }
                }

                showRemoveOsModal(point) {
                    const modal = document.createElement('div');
                    modal.id = 'remove-os-modal';
                    modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[9999]';
                    modal.innerHTML = `
                        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 overflow-hidden">
                            <div class="bg-gradient-to-r from-amber-500 to-orange-600 p-4">
                                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    Remover OS ${point.osNumber}
                                </h3>
                            </div>
                            <div class="p-6">
                                <p class="text-gray-600 mb-6">Deseja retirar essa OS do roteiro? O status dela retornará para <strong>ABERTA</strong> e ela ficará disponível para novo agendamento.</p>
                            </div>
                            <div class="bg-gray-50 px-6 py-3 flex justify-end gap-3">
                                <button id="cancel-modal-btn" class="px-4 py-2 text-gray-500 hover:text-gray-700 font-medium">Cancelar</button>
                                <button id="close-os-btn" class="py-2 px-4 bg-amber-50 hover:bg-amber-100 rounded-xl text-amber-700 font-medium transition-all flex items-center gap-2 border border-amber-200 shadow-sm">
                                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Retirar do Roteiro
                                </button>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(modal);

                    document.getElementById('cancel-modal-btn').onclick = () => modal.remove();
                    document.getElementById('close-os-btn').onclick = async () => {
                        modal.remove();
                        await this.encerrarOs(point.osNumber);
                    };
                }

                async encerrarOs(osNumber) {
                    try {
                        const response = await fetch('{{ route("rotas.encerrar-os") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ os_number: osNumber, remove_from_route: true })
                        });
                        const data = await response.json();
                        if (data.success) {
                            // Remove from local points
                            this.points = this.points.filter(p => p.osNumber !== osNumber);
                            this.redrawMap();
                            this.saveToStorage();
                            this.updateUI();
                            this.showSuccess('OS removida do roteiro com sucesso!');
                        } else {
                            this.showError(data.message || 'Erro ao remover OS do roteiro');
                        }
                    } catch (error) {
                        console.error('Error removing OS from route:', error);
                        this.showError('Erro ao remover OS do roteiro');
                    }
                }

                clearRoute() {
                    if (this.points.length === 0) return;

                    if (confirm('Tem certeza que deseja limpar toda a rota?')) {
                        // Remove all markers
                        this.markers.forEach(m => this.map.removeLayer(m));
                        this.markers = [];

                        // Remove route line
                        if (this.routeLine) {
                            this.map.removeLayer(this.routeLine);
                            this.routeLine = null;
                        }

                        // Clear points
                        this.points = [];

                        // Reload default points
                        this.loadDefaultPoints();

                        this.saveToStorage();
                        this.updateUI();
                        this.showSuccess('Rota limpa com sucesso!');
                    }
                }

                updateUI() {
                    // Update points count (excluding default points)
                    const osPoints = this.points.filter(p => p.osNumber).length;
                    document.getElementById('points-count-badge').textContent = `${osPoints}`;

                    // Update points list
                    const pointsList = document.getElementById('points-list');

                    if (this.points.length === 0) {
                        pointsList.innerHTML = '<p class="text-gray-400 text-xs text-center py-8">Nenhum ponto adicionado</p>';
                        if (document.getElementById('total-distance')) document.getElementById('total-distance').textContent = '0 km';
                        if (document.getElementById('total-duration')) document.getElementById('total-duration').textContent = '0 min';
                        if (document.getElementById('total-service-time')) document.getElementById('total-service-time').textContent = '0 min';
                        if (document.getElementById('return-time')) document.getElementById('return-time').textContent = '--:--';
                    } else {
                        pointsList.innerHTML = this.points.map((point, index) => {
                            // Calcular sequência apenas para OSs
                            const osPoints = this.points.filter(p => p.osNumber && !p.isDefault);
                            const osIndex = osPoints.indexOf(point);
                            const sequenceNumber = osIndex >= 0 ? osIndex + 1 : null;
                            const displayClientName = this.normalizeClientName(
                                point.clientName || (point.isStart ? 'Ponto de Partida' : point.isEnd ? 'Ponto Final' : 'Cliente Sem Nome')
                            );

                            const isDraggable = !point.isStart && !point.isEnd;
                            return `
<div class="group relative bg-white border border-gray-100 rounded-xl p-3 transition-all hover:border-red-200 hover:shadow-sm ${!isDraggable ? 'no-drag bg-gray-50/50' : ''}" data-id="${point.id}">
    <div class="flex items-center gap-3">

        <!-- Content -->
        <div class="flex-1 min-w-0">
            <!-- Header: Client Name (Primary Focus) -->
            <div class="flex items-center justify-between gap-2 mb-0.5">
                <div class="font-bold text-gray-900 text-sm leading-tight truncate" title="${point.clientName || ''}">
                    ${displayClientName}
                </div>
                <!-- Sequence Badge -->
                <span class="flex-shrink-0 w-5 h-5 ${point.isStart ? 'bg-emerald-500' : point.isEnd ? 'bg-rose-500' : 'bg-red-600'} text-white rounded-full flex items-center justify-center text-[10px] font-bold shadow-sm">
                    ${point.isStart ? '🏁' : point.isEnd ? '🏁' : sequenceNumber}
                </span>
            </div>

            <!-- Subheader: Address -->
            <div class="text-xs text-gray-500 mb-1.5 leading-snug line-clamp-2" title="${point.address}">
                ${point.address}
            </div>

            <!-- Details Row -->
            <div class="flex flex-wrap items-center gap-1.5">
                ${point.osNumber ? `
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-red-50 text-red-700 text-[10px] font-bold border border-red-100">
                        OS ${point.osNumber}
                    </span>
                ` : ''}

                ${point.serviceTimeMinutes > 0 ? `
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 text-[10px] font-medium">
                        ${Math.floor(point.serviceTimeMinutes / 60)}h ${point.serviceTimeMinutes % 60}min
                    </span>
                ` : ''}

                ${point.alertaHorario ? `
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 text-[10px] font-medium border border-rose-100">
                        Até ${point.alertaHorario}
                    </span>
                ` : ''}

                ${point.helpers && point.helpers.length > 0 ? `
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 text-[10px] font-bold border border-blue-100">
                        + ${point.helpers.join(', ')}
                    </span>
                ` : ''}
            </div>
        </div>

        <!-- Right: Actions & Drag Handle -->
        <div class="flex flex-col items-center gap-1 pl-2 border-l border-gray-100 self-stretch justify-center">
            ${isDraggable ? `
            <div class="drag-handle cursor-grab active:cursor-grabbing p-1 text-gray-300 hover:text-red-500 hover:bg-red-50 rounded transition-all" title="Arrastar para reordenar">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
                </svg>
            </div>
            ` : ''}

            ${point.isDefault ? `
            <button 
                onclick="routePlanner.openEditDefaultPointModal('${point.id}')"
                class="p-1 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-all"
                title="Editar ponto padrão"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </button>
            ` : ''}

            ${!point.isDefault && point.osNumber ? `
            <button 
                onclick="routePlanner.openEditModal('${point.id}')"
                class="p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-all"
                title="Editar detalhes"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </button>
            ` : ''}

            ${!point.isDefault ? `
            <button 
                onclick="routePlanner.removePoint(${point.id})"
                class="p-1 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded transition-all"
                title="Remover ponto"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
            ` : ''}
        </div>
    </div>
</div>
                                                                                                                                                                                                                                    `}).join('');

                        // Reinitialize sortable after updating DOM
                        if (this.sortable) {
                            this.sortable.destroy();
                        }
                        this.initSortable();
                    }
                }

                updateSummaryDisplay() {
                    // Calculate total service time
                    const totalServiceTime = this.points.reduce((sum, p) => sum + (p.serviceTimeMinutes || 0), 0);

                    // Update service time display
                    if (document.getElementById('total-service-time')) {
                        document.getElementById('total-service-time').textContent = `${Math.floor(totalServiceTime / 60)}h ${totalServiceTime % 60}min`;
                    }

                    // Recalculate return time if we have duration data
                    const durationElement = document.getElementById('total-duration');
                    if (durationElement) {
                        const durationText = durationElement.textContent;
                        const durationMatch = durationText.match(/(\d+)/);
                        if (durationMatch) {
                            const durationMin = parseInt(durationMatch[1]);
                            const totalTimeMin = durationMin + totalServiceTime;

                            // Calculate return time (assuming start at 8:00 AM)
                            const startTime = new Date();
                            startTime.setHours(8, 0, 0, 0);
                            const returnTime = new Date(startTime.getTime() + totalTimeMin * 60000);

                            if (document.getElementById('return-time')) {
                                document.getElementById('return-time').textContent = `${returnTime.getHours().toString().padStart(2, '0')}:${returnTime.getMinutes().toString().padStart(2, '0')}`;
                            }
                        }
                    }
                }

                async fetchLatestOs() {
                    const url = new URL('{{ route("rotas.latest-os") }}', window.location.origin);
                    url.searchParams.append('page', this.osPage);

                    url.searchParams.append('Setor', 'ASSISTENCIA');

                    const searchClient = document.getElementById('os-search-client').value;
                    const searchNumber = document.getElementById('os-search-number').value;
                    const searchDay = document.getElementById('os-search-day').value;

                    if (searchClient) url.searchParams.append('search', searchClient);
                    if (searchNumber) url.searchParams.append('number', searchNumber);
                    if (searchDay) url.searchParams.append('day', searchDay);

                    if (this.latestOsAbortController) {
                        this.latestOsAbortController.abort();
                    }

                    const abortController = new AbortController();
                    this.latestOsAbortController = abortController;
                    document.getElementById('latest-os-list').innerHTML = `
                        <div class="flex items-center justify-center py-8">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-red-600"></div>
                        </div>
                    `;

                    try {
                        const response = await fetch(url, {
                            signal: abortController.signal
                        });
                        const result = await response.json();

                        if (result.success) {
                            this.osLastPage = result.last_page;
                            this.renderLatestOs(result.data);
                            this.updateOsPagination();
                        } else {
                            document.getElementById('latest-os-list').innerHTML = '<p class="text-gray-500 text-sm text-center py-4">Não foi possível carregar as OS.</p>';
                        }
                    } catch (error) {
                        if (error.name === 'AbortError') {
                            return;
                        }

                        console.error('Error fetching latest OS:', error);
                        document.getElementById('latest-os-list').innerHTML = '<p class="text-gray-500 text-sm text-center py-4">Erro ao carregar as OS.</p>';
                    } finally {
                        if (this.latestOsAbortController === abortController) {
                            this.latestOsAbortController = null;
                        }
                    }
                }

                renderLatestOs(ordens) {
                    const list = document.getElementById('latest-os-list');
                    if (ordens.length === 0) {
                        list.innerHTML = '<p class="text-gray-500 text-sm text-center py-4">Nenhuma OS encontrada</p>';
                        return;
                    }

                    list.innerHTML = ordens.map(os => {
                        const cliente = os.cliente_by_codigo;

                        // Build address parts, filtering out null/undefined values
                        const addressParts = [];
                        if (cliente) {
                            if (cliente.Endereco) addressParts.push(cliente.Endereco);
                            if (cliente.Numero) addressParts.push(cliente.Numero);
                            if (cliente.Bairro) addressParts.push(cliente.Bairro);
                            if (cliente.Cidade) addressParts.push(cliente.Cidade);
                            if (cliente.Uf) addressParts.push(cliente.Uf);
                        }

                        const endereco = addressParts.length > 0 ? addressParts.join(', ') : 'Endereço não disponível';
                        const hasValidAddress = addressParts.length >= 2; // Need at least street and city

                        // Clean client name by removing suffixes
                        let clientName = cliente ? cliente['Nome conhecido'] : 'Cliente Desconhecido';
                        clientName = this.normalizeClientName(clientName);

                        const safeEndereco = endereco.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        const safeClientName = (cliente ? cliente['Nome conhecido'] : '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        const safeCep = (cliente?.Cep || '').replace(/"/g, '&quot;');
                        const safeAlerta = (os.ordem_api?.alerta_horario || '').replace(/"/g, '&quot;');
                        const helpersJson = JSON.stringify([os.ordem_api?.ajudante1, os.ordem_api?.ajudante2, os.ordem_api?.ajudante3, os.ordem_api?.ajudante4].filter(Boolean));

                        return `
<div class="bg-gray-50 rounded-xl p-3 border border-gray-100 hover:border-red-200 transition-all group">
    <div class="flex justify-between items-center gap-2">
        <div class="flex-1 min-w-0">
            <div class="text-xs font-bold text-red-600 mb-1">OS ${os['Numero ordem']}</div>
            <div class="text-sm font-semibold text-gray-800 break-words">${clientName}</div>
        </div>
        ${hasValidAddress ? `
        <button 
            data-os-number="${os['Numero ordem']}"
            data-endereco="${safeEndereco}"
            data-cep="${safeCep}"
            data-client-name="${safeClientName}"
            data-tempo="${os.ordem_api?.tempo_estimado || 60}"
            data-alerta="${safeAlerta}"
            data-helpers='${helpersJson}'
            onclick="
                var btn = this;
                routePlanner.addOsToRoute(
                    btn.dataset.osNumber,
                    btn.dataset.endereco,
                    btn.dataset.cep,
                    btn.dataset.clientName,
                    parseInt(btn.dataset.tempo),
                    btn.dataset.alerta,
                    true,
                    JSON.parse(btn.dataset.helpers)
                );
            "
            class="flex-shrink-0 text-[10px] bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700 transition-colors opacity-0 group-hover:opacity-100"
        >
            + Rota
        </button>
        ` : `
        <span class="flex-shrink-0 text-[10px] text-gray-400 px-2 py-1">Sem endereço</span>
        `}
    </div>
</div>
`;
                    }).join('');
                }

                updateOsPagination() {
                    document.getElementById('os-page-info').textContent = `${this.osPage}/${this.osLastPage}`;
                    document.getElementById('prev-os-page').disabled = this.osPage <= 1;
                    document.getElementById('next-os-page').disabled = this.osPage >= this.osLastPage;
                }

                async loadPreSelectedOs(osNumbersArray) {
                    try {
                        const preSelected = osNumbersArray.slice(0, 8);
                        if (osNumbersArray.length > 8) {
                            this.showError('Foram detectadas ' + osNumbersArray.length + ' OS, mas apenas as primeiras 8 foram adicionadas devido ao limite.');
                        }

                        const qs = preSelected.map(id => `os_in[]=${encodeURIComponent(id)}`).join('&');
                        const response = await fetch("{{ route('rotas.mapa-geral-data') }}?" + qs);
                        const result = await response.json();
                        
                        if (result.success && result.data && result.data.length > 0) {
                            for (const os of result.data) {
                                await this.addOsToRoute(
                                    os.numero_ordem,
                                    os.endereco,
                                    os.cep,
                                    os.cliente,
                                    os.tempo_estimado || 60,
                                    '', // alerta
                                    false, // showMessages
                                    [], // helpers
                                    false // recalculate
                                );
                            }
                            this.recalculateRoute(); // Recalculate once at the end
                        }
                    } catch (e) {
                         console.error('Erro ao processar OS pré-selecionadas:', e);
                    }
                }

                async addOsToRoute(osNumber, address, cep = '', clientName = '', serviceTimeMinutes = 60, alertaHorario = '', showMessages = true, osHelpers = [], recalculateRoute = true) {
                    const osCount = this.points.filter(p => p.osNumber && !p.isDefault).length;
                    if (osCount >= 8) {
                        this.showError('O limite de 8 OS por roteiro foi atingido.');
                        if (showMessages) this.showLoading(false);
                        return;
                    }

                    if (showMessages) this.showLoading(true);
                    try {
                        const geocodeData = await this.resolveGeocode({
                            address,
                            cep,
                            numero: this.extractStreetNumber(address)
                        });

                        if (geocodeData?.success) {
                            // Use a more complete address for display if possible
                            const displayAddress = geocodeData.data.display_name || address;

                            const point = this.createPoint({
                                address: displayAddress,
                                lat: geocodeData.data.lat,
                                lon: geocodeData.data.lon,
                                technician: this.currentTechnician,
                                osNumber: osNumber,
                                clientName: clientName,
                                serviceTimeMinutes: parseInt(serviceTimeMinutes) || 60,
                                alertaHorario: alertaHorario,
                                helpers: osHelpers,
                                isDefault: false,
                                isStart: false,
                                isEnd: false
                            });

                            // Logic to auto-populate header helpers if currently empty
                            this.applyHeaderHelpers(osHelpers);

                            if (this.points.length >= 2) {
                                this.points.splice(this.points.length - 1, 0, point);
                            } else {
                                this.points.push(point);
                            }

                            this.addMarker(point);
                            this.saveToStorage();
                            this.updateUI();

                            if (recalculateRoute && this.points.length >= 2) {
                                await this.calculateRoute({
                                    showLoading: showMessages,
                                    notifyOnError: showMessages
                                });
                            }
                            if (showMessages) this.showSuccess(`OS ${osNumber} adicionada à rota!`);
                            return true;
                        } else {
                            if (showMessages) this.showError(geocodeData.message || 'Não foi possível geocodificar o endereço da OS. Tente adicionar manualmente clicando no mapa.');
                            return false;
                        }
                    } catch (error) {
                        console.error('Error adding OS to route:', error);
                        if (showMessages) {
                            this.showError('Erro ao adicionar OS: ' + error.message);
                        }
                        return false;
                    } finally {
                        if (showMessages) this.showLoading(false);
                    }
                }

                clearForm() {
                    // Clear address inputs
                    const addressInput = document.getElementById('address-input');
                    const cepInput = document.getElementById('cep-input');
                    const numeroInput = document.getElementById('numero-input');
                    const serviceHoursInput = document.getElementById('service-hours-input');
                    const serviceMinutesInput = document.getElementById('service-minutes-input');

                    if (addressInput) addressInput.value = '';
                    if (cepInput) cepInput.value = '';
                    if (numeroInput) numeroInput.value = '';
                    if (serviceHoursInput) serviceHoursInput.value = '0';
                    if (serviceMinutesInput) serviceMinutesInput.value = '30';
                }

                saveToStorage() {
                    // Only save start and end points as requested
                    const pointsToSave = this.points.filter(p => p.isStart || p.isEnd);
                    localStorage.setItem('route_planner_points', JSON.stringify(pointsToSave));
                }

                loadFromStorage() {
                    const saved = localStorage.getItem('route_planner_points');
                    if (saved) {
                        try {
                            const savedPoints = JSON.parse(saved);

                            // Only load non-default points (default points are added separately)
                            const userPoints = savedPoints.filter(p => !p.isDefault);

                            userPoints.forEach(point => {
                                this.points.push(point);
                                this.addMarker(point);
                            });

                            if (this.points.length >= 2) {
                                this.calculateRoute();
                            }
                        } catch (error) {
                            console.error('Error loading from storage:', error);
                        }
                    }
                }

                showLoading(show, options = {}) {
                    const overlay = document.getElementById('loading-overlay');
                    const message = document.getElementById('loading-message');
                    const detail = document.getElementById('loading-detail');

                    if (message) {
                        message.textContent = options.message || 'Processando...';
                    }

                    if (detail) {
                        detail.textContent = options.detail || '';
                        detail.classList.toggle('hidden', !options.detail);
                    }

                    overlay.classList.toggle('hidden', !show);
                }

                showSuccess(message) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        alert(message);
                    }
                }

                showError(message) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: message
                        });
                    } else {
                        alert(message);
                    }
                }

                async saveRoute() {
                    const technicianId = document.getElementById('filter-technician').value;
                    const carId = document.getElementById('filter-car').value;
                    const day = this.datePicker.selectedDates.length > 0
                        ? this.datePicker.formatDate(this.datePicker.selectedDates[0], 'Y-m-d')
                        : null;
                    const osList = this.points
                        .filter(p => p.osNumber)
                        .map(p => ({
                            number: p.osNumber,
                            tempo_estimado: p.serviceTimeMinutes,
                            alerta_horario: p.alertaHorario
                        }));

                    if (!technicianId || !carId || !day) {
                        const missing = [];
                        if (!technicianId) missing.push('Técnico');
                        if (!carId) missing.push('Carro');
                        if (!day) missing.push('Dia');

                        const missingText = missing.length === 1
                            ? missing[0]
                            : missing.slice(0, -1).join(', ') + ' e ' + missing[missing.length - 1];

                        this.showError(`Por favor, selecione o ${missingText}.`);
                        return;
                    }

                    if (osList.length === 0) {
                        this.showError('Adicione pelo menos uma OS à rota.');
                        return;
                    }

                    if (osList.length > 20) {
                        this.showError('Limite de 20 visitas por roteiro atingido.');
                        return;
                    }

                    this.showLoading(true);

                    try {
                        const response = await fetch('{{ route("rotas.save") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                technician_id: technicianId,
                                car_id: carId,
                                day: day,
                                os_list: osList,
                                numero_roteiro: this.numeroRoteiro,
                                ajudante1: document.getElementById('filter-ajudante1').value,
                                ajudante2: document.getElementById('filter-ajudante2').value,
                                ajudante3: document.getElementById('filter-ajudante3').value,
                                ajudante4: document.getElementById('filter-ajudante4').value
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            this.showSuccess(result.message);
                            // Optionally clear route or redirect
                        } else {
                            this.showError(result.message);
                        }
                    } catch (error) {
                        console.error('Error saving route:', error);
                        this.showError('Erro ao salvar roteiro.');
                    } finally {
                        this.showLoading(false);
                    }
                }

                // Edit Modal Functions
                async openEditModal(pointId) {
                    const point = this.points.find(p => p.id == pointId);
                    if (!point) return;

                    const tecOptions = this.tecnicos.map(t => `<option value="${t.nome}" ${t.nome === point.technician ? 'selected' : ''}>${t.nome}</option>`).join('');

                    const { value: formValues } = await Swal.fire({
                        title: `Editar OS ${point.osNumber}`,
                        html: `
<div class="text-left">
    <div class="mb-4">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tempo Estimado (minutos)</label>
        <input type="number" id="swal-tempo-estimado" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-red-500/20 outline-none transition-all" placeholder="Ex: 60" value="${point.serviceTimeMinutes || 60}">
    </div>

    <div class="mb-4">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Alerta de Horário</label>
        <input type="time" id="swal-alerta-horario" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-red-500/20 outline-none transition-all" value="${point.alertaHorario || ''}">
        <p class="text-[10px] text-gray-400 mt-1">Alertar caso o serviço não esteja pronto até este horário</p>
    </div>

    <div class="mb-4">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Técnico</label>
        <select id="swal-novo-tecnico" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-red-500/20 outline-none transition-all">
            ${tecOptions}
        </select>
    </div>

    <div class="mb-4">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Reagendar para</label>
        <div class="grid grid-cols-1 gap-2">
             <input type="text" id="swal-reagendar-data" 
                    class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-red-500/20 outline-none transition-all" 
                    placeholder="Selecione a nova data (Segunda, Terça, etc.)">
        </div>
        <p class="text-[10px] text-gray-400 mt-1">Ao selecionar uma data ou trocar de técnico, a OS será movida automaticamente para a rota correspondente</p>
    </div>
                                                                                                                                                                                                                                 </div>
                                                                                                                                                                                                                             `,
                        focusConfirm: false,
                        showCancelButton: true,
                        confirmButtonText: 'Salvar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#2563EB',
                        didOpen: () => {
                            // Initialize Flatpickr for rescheduling
                            flatpickr("#swal-reagendar-data", {
                                locale: "pt",
                                dateFormat: "Y-m-d",
                                altInput: true,
                                altFormat: "l, d/m/Y",
                                allowInput: false,
                                minDate: "today"
                            });

                            // Add real-time update listener
                            const tempoInput = document.getElementById('swal-tempo-estimado');
                            tempoInput.addEventListener('input', (e) => {
                                const newTime = parseInt(e.target.value) || 0;
                                // Update the point temporarily for preview
                                const pointIndex = this.points.findIndex(p => p.id == pointId);
                                if (pointIndex !== -1) {
                                    const oldTime = this.points[pointIndex].serviceTimeMinutes;
                                    this.points[pointIndex].serviceTimeMinutes = newTime;

                                    // Update summary display
                                    this.updateSummaryDisplay();

                                    // Store old value in case user cancels
                                    tempoInput.dataset.oldValue = oldTime;
                                }
                            });
                        },
                        preConfirm: () => {
                            return {
                                tempoEstimado: document.getElementById('swal-tempo-estimado').value,
                                alertaHorario: document.getElementById('swal-alerta-horario').value,
                                reagendarData: document.getElementById('swal-reagendar-data').value,
                                novoTecnico: document.getElementById('swal-novo-tecnico').value
                            }
                        },
                        willClose: () => {
                            // If user cancels, restore old value
                            const tempoInput = document.getElementById('swal-tempo-estimado');
                            if (Swal.getConfirmButton() && !Swal.getConfirmButton().disabled) {
                                // User clicked confirm, keep the new value
                            } else {
                                // User cancelled, restore old value
                                const oldValue = parseInt(tempoInput.dataset.oldValue);
                                if (!isNaN(oldValue)) {
                                    const pointIndex = this.points.findIndex(p => p.id == pointId);
                                    if (pointIndex !== -1) {
                                        this.points[pointIndex].serviceTimeMinutes = oldValue;
                                        this.updateSummaryDisplay();
                                    }
                                }
                            }
                        }
                    });

                    if (formValues) {
                        const pointIndex = this.points.findIndex(p => p.id == pointId);
                        if (pointIndex !== -1) {
                            const dateChanged = formValues.reagendarData && formValues.reagendarData !== '';
                            const techChanged = formValues.novoTecnico !== point.technician;

                            // Check if rescheduling was selected or technician changed
                            if (dateChanged || techChanged) {
                                const routeCarSelect = document.getElementById('filter-car');
                                const routeCarId = routeCarSelect?.value || '';

                                if (!routeCarId) {
                                    routeCarSelect?.focus();
                                    this.showError('Selecione o Carro antes de mover a OS no roteiro.');
                                    return;
                                }

                                // Call reschedule endpoint
                                this.showLoading(true);
                                try {
                                    const response = await fetch('{{ route("rotas.reschedule-os") }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                        },
                                        body: JSON.stringify({
                                            os_number: point.osNumber,
                                            new_date: dateChanged ? formValues.reagendarData : this.selectedDate, // Use current date if only tech changed
                                            new_tecnico: formValues.novoTecnico,
                                            car_id: routeCarId
                                        })
                                    });

                                    const result = await response.json();

                                    if (result.success) {
                                        // Remove point from current view
                                        this.removePoint(point.id, true);
                                        const msg = techChanged ? `OS ${point.osNumber} movida para ${formValues.novoTecnico}!` : `OS ${point.osNumber} reagendada com sucesso!`;
                                        this.showSuccess(msg);
                                    } else {
                                        this.showError(result.message || 'Erro ao reagendar OS.');
                                    }
                                } catch (error) {
                                    console.error('Error rescheduling OS:', error);
                                    this.showError('Erro ao reagendar OS: ' + error.message);
                                } finally {
                                    this.showLoading(false);
                                }
                            } else {
                                // Normal update without rescheduling
                                this.points[pointIndex].serviceTimeMinutes = parseInt(formValues.tempoEstimado);
                                this.points[pointIndex].alertaHorario = formValues.alertaHorario;

                                this.updateUI();
                                this.saveToStorage();
                                this.updateSummaryDisplay();
                                await this.calculateRoute();
                                this.showSuccess('Detalhes atualizados!');
                            }
                        }
                    }
                }

                // Edit Default Point Modal
                async openEditDefaultPointModal(pointId) {
                    const point = this.points.find(p => p.id == pointId);
                    if (!point) return;

                    const pointType = point.isStart ? 'Inicial' : 'Final';
                    const currentName = point.isStart ? 'Tecnoar Galpão' : 'Tecnoar Matriz';

                    const { value: formValues } = await Swal.fire({
                        title: `Editar Ponto ${pointType}`,
                        html: `
<div class="text-left">
    <div class="mb-3">
        <p class="text-xs text-gray-500 mb-3">Ponto atual: <strong>${currentName}</strong></p>
    </div>

    <div class="mb-4">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Endereço ou CEP</label>
        <input type="text" id="swal-address" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 outline-none transition-all" placeholder="Rua, Bairro, Cidade ou 00000-000">
        <p class="text-[10px] text-gray-400 mt-1">Digite o endereço completo ou apenas o CEP</p>
    </div>

    <div class="mb-4">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Número</label>
        <input type="text" id="swal-numero" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500/20 outline-none transition-all" placeholder="Nº (obrigatório para CEP)">
    </div>
</div>
                                                                                                                                                                                                                                    `,
                        focusConfirm: false,
                        showCancelButton: true,
                        confirmButtonText: 'Salvar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#3B82F6',
                        preConfirm: () => {
                            const address = document.getElementById('swal-address').value;
                            if (!address) {
                                Swal.showValidationMessage('Por favor, insira um endereço ou CEP');
                                return false;
                            }
                            return {
                                address: address,
                                numero: document.getElementById('swal-numero').value
                            }
                        }
                    });

                    if (formValues) {
                        await this.updateDefaultPoint(pointId, formValues.address, formValues.numero);
                    }
                }

                async updateDefaultPoint(pointId, address, numero) {
                    this.showLoading(true);

                    try {
                        let geocodeData = null;

                        // Check if it's a CEP
                        const cepMatch = address.replace(/\D/g, '').match(/^(\d{8})$/);

                        if (cepMatch) {
                            const cep = cepMatch[1];
                            if (!numero) {
                                this.showError('Por favor, insira o número para o CEP');
                                this.showLoading(false);
                                return;
                            }

                            const response = await fetch('{{ route("rotas.geocode-cep") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ cep, numero })
                            });

                            geocodeData = await response.json();
                        } else {
                            // It's an address
                            if (numero) {
                                address += `, ${numero}`;
                            }

                            const response = await fetch('{{ route("rotas.geocode") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ address })
                            });

                            geocodeData = await response.json();
                        }

                        if (geocodeData?.success) {
                            const pointIndex = this.points.findIndex(p => p.id == pointId);
                            if (pointIndex !== -1) {
                                // Update coordinates and address, but keep isStart/isEnd/isDefault flags
                                this.points[pointIndex].lat = geocodeData.data.lat;
                                this.points[pointIndex].lon = geocodeData.data.lon;
                                this.points[pointIndex].address = geocodeData.data.display_name || address;
                                // Update clientName to reflect the change in the UI header
                                this.points[pointIndex].clientName = geocodeData.data.display_name || address;

                                // Redraw map and recalculate route
                                this.redrawMap();
                                this.updateUI(); // Update the list to show new description
                                // Do NOT save to storage as requested
                                this.showSuccess('Ponto atualizado com sucesso!');
                            }
                        } else {
                            this.showError(geocodeData.message || 'Não foi possível geocodificar o endereço.');
                        }
                    } catch (error) {
                        console.error('Error updating default point:', error);
                        this.showError('Erro ao atualizar ponto: ' + error.message);
                    } finally {
                        this.showLoading(false);
                    }
                }
            }

            // Initialize route planner when page loads
            let routePlanner;
            document.addEventListener('DOMContentLoaded', () => {
                routePlanner = new RoutePlanner();
            });
        </script>
    @endpush
@endsection
