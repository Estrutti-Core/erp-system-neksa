@extends('layouts.app2')

@section('content')
<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-col h-[calc(100vh-30px)] overflow-hidden">
    <!-- Header with Filters -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4 flex-shrink-0">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div>
                <a href="{{ route('rotas.index') }}"
                    class="inline-flex items-center gap-2 text-xs font-bold text-gray-400 hover:text-red-600 mb-2 uppercase tracking-wide transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z"
                            clip-rule="evenodd" />
                    </svg>
                    Voltar para Listagem
                </a>
                <h1 class="text-2xl font-bold text-gray-900">Mapa Geral de OS</h1>
                <p class="text-sm text-gray-500 mt-1">Visualize e agrupe serviços</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1 ml-1">Buscar</label>
                    <input type="text" id="filter-search" placeholder="Cliente ou endereço..."
                        class="w-full bg-gray-50 border-none rounded-xl py-2 px-4 text-xs font-bold focus:ring-2 focus:ring-red-500/20 transition-all" />
                </div>

                <div class="flex-1 min-w-[180px]">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1 ml-1">Técnico</label>
                    <select id="filter-technician"
                        class="w-full bg-gray-50 border-none rounded-xl py-2 px-4 text-xs font-bold focus:ring-2 focus:ring-red-500/20 transition-all cursor-pointer">
                        <option value="">Selecione o Técnico</option>
                        @foreach($tecnicos as $tecnico)
                            <option value="{{ $tecnico->codigo }}">{{ $tecnico->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1 ml-1">Carro</label>
                    <select id="filter-car"
                        class="w-full bg-gray-50 border-none rounded-xl py-2 px-4 text-xs font-bold focus:ring-2 focus:ring-red-500/20 transition-all cursor-pointer">
                        <option value="">Selecione o Carro</option>
                        @foreach($carros as $carro)
                            <option value="{{ $carro->id }}">
                                {{ $carro->apelido }}{{ $carro->placa ? ' - ' . $carro->placa : '' }}{{ !$carro->placa && $carro->modelo ? ' - ' . $carro->modelo : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1 ml-1">Dia da Visita</label>
                    <input type="text" id="filter-day" placeholder="Selecione o dia..."
                        class="w-full bg-gray-50 border-none rounded-xl py-2 px-4 text-xs font-bold focus:ring-2 focus:ring-red-500/20 transition-all cursor-pointer"
                        readonly />
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 min-h-0 flex flex-col lg:flex-row gap-4 mb-4">
        
        <!-- Sidebar: Selection, Legend, Details -->
        <div class="w-full lg:w-1/4 flex flex-col gap-4 overflow-y-auto pr-2 custom-scrollbar min-h-0">
            
            <!-- Selection Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-col flex-shrink-0 relative">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-gray-800">OS Selecionadas</h2>
                    <span id="selected-count-badge" class="bg-red-100 text-red-600 text-[10px] font-bold px-2 py-0.5 rounded-full">0</span>
                </div>
                
                <div id="selection-list" class="max-h-[300px] overflow-y-auto space-y-2 pr-1 custom-scrollbar min-h-0">
                    <p class="text-gray-400 text-xs text-center mt-8">Nenhuma OS selecionada.<br>Clique nos marcadores vermelhos no mapa.</p>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100">
                    <button id="btn-atribuir" disabled class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg text-xs transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Criar Roteiro
                    </button>
                    <button id="btn-limpar-selecao" class="w-full mt-2 bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold py-2 rounded-lg text-xs transition-all flex items-center justify-center hidden">
                        Limpar Seleção
                    </button>
                </div>
            </div>

            <!-- Legend Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex-shrink-0">
                <h2 class="text-sm font-bold text-gray-800 mb-3 uppercase tracking-tighter">Legenda</h2>
                <div class="space-y-1" id="legend-container">
                    <!-- Legend content -->
                </div>
            </div>
            
            <!-- Tech Details Card (Hidden by default) -->
            <div id="tech-details-card" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex-shrink-0 hidden animate-in fade-in slide-in-from-right duration-300">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold text-gray-800" id="tech-details-name">Detalhes do Técnico</h2>
                    <button onclick="window.mapApp.closeTechDetails()" class="text-gray-400 hover:text-red-600 transition-colors cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div id="tech-os-list" class="space-y-3">
                    <!-- OS entries will be injected here -->
                </div>
            </div>
        </div>

        <!-- Map Area -->
        <div class="w-full lg:w-3/4 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden relative flex-1 min-h-[500px] lg:min-h-0">
            <div id="map" class="w-full h-full" style="min-height: 500px;"></div>
            
            <!-- Loading Indicator mapped over the map -->
            <div id="map-loader" class="absolute inset-0 bg-white/70 z-[1000] flex flex-col items-center justify-center">
                <svg class="animate-spin h-8 w-8 text-red-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-sm font-bold text-gray-800" id="loader-text">Carregando dados...</span>
            </div>
        </div>

    </div>
</div>

@push('scripts')
<!-- Flatpickr CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    /* Custom Map Markers styles for custom colors using Leaflet DivIcon */
    .custom-marker {
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        border: 2px solid white;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        position: relative;
    }
    .custom-marker:hover {
        transform: scale(1.15);
        z-index: 1000 !important;
    }
    .custom-marker.selected {
        border-color: #000 !important;
        border-width: 3px !important;
        transform: scale(1.3) !important;
        z-index: 2000 !important;
        box-shadow: 0 0 15px rgba(0,0,0,0.5) !important;
        outline: 2px solid white;
    }
    .marker-label {
        position: absolute;
        top: 22px;
        left: 50%;
        transform: translateX(-50%);
        background: white;
        padding: 1px 5px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: 800;
        color: #374151;
        white-space: nowrap;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border: 1px solid #e5e7eb;
        pointer-events: none;
        z-index: 10;
    }
    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #e5e7eb;
        border-radius: 4px;
    }
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(0, 0, 0, 0.4); }
        70% { box-shadow: 0 0 0 6px rgba(0, 0, 0, 0); }
        100% { box-shadow: 0 0 0 0 rgba(0, 0, 0, 0); }
    }
    @keyframes pulse-marker {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { transform: scale(1.15); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }
    .pulse-marker {
        z-index: 1000 !important;
    }
    .pulse-marker > div:first-child {
        animation: pulse-marker 2s infinite !important;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.3);
    }
    /* Tecnoar Branch Markers */
    .tecnoar-branch-marker {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: 3px solid white;
        box-shadow: 0 4px 12px rgba(0,0,0,0.25);
        font-size: 14px;
        z-index: 900;
    }
    .tecnoar-branch-label {
        position: absolute;
        top: 30px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(255,255,255,0.95);
        padding: 2px 7px;
        border-radius: 5px;
        font-size: 9px;
        font-weight: 900;
        color: #111827;
        white-space: nowrap;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        border: 1px solid #e5e7eb;
        pointer-events: none;
    }
</style>

<script>
    class GlobalOSMap {
        constructor() {
            this.map = null;
            this.markers = {};
            this.osData = [];
            this.selectedOsIds = new Set();
            this.techColors = {};
            this.defaultColor = '#ef4444'; // Red
            this.colorPalette = [
                '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', 
                '#06b6d4', '#84cc16', '#6366f1', '#14b8a6', '#f43f5e',
                '#0ea5e9', '#d946ef', '#22c55e', '#eab308', '#64748b'
            ];
            this.colorIndex = 0;
            this.hiddenTechs = new Set();
            
            this.init();
        }

        init() {
            this.initMap();
            this.attachEvents();
            this.loadData();
        }

        initMap() {
            this.map = L.map('map', {
                zoomControl: false 
            }).setView([-23.5505, -46.6333], 11);
            
            L.control.zoom({ position: 'bottomright' }).addTo(this.map);

            const lightLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                subdomains: 'abcd', maxZoom: 20, attribution: '© OpenStreetMap contributors © CARTO'
            });
            const darkLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                subdomains: 'abcd', maxZoom: 20, attribution: '© OpenStreetMap contributors © CARTO'
            });

            const THEME_KEY = 'theme';
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
                return hasDarkClass() || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
            };

            if (isDark()) darkLayer.addTo(this.map);
            else lightLayer.addTo(this.map);

            this._tileLayers = { lightLayer, darkLayer, isDark };

            this._darkObserver = new MutationObserver(() => {
                setStoredTheme(hasDarkClass() ? 'dark' : 'light');
                this.updateMapTheme();
            });

            this._darkObserver.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class']
            });

            // Add fixed Tecnoar branch markers
            this.addBranchMarkers();
        }

        addBranchMarkers() {
            const branches = [
                {
                    name: 'Tecnoar Matriz',
                    lat: -23.5087263,
                    lon: -46.5879472,
                    color: '#dc2626',
                    emoji: '★',
                    address: 'Av. Inajar de Souza - Vila Maria, São Paulo - SP, CEP 02125-001'
                },
                {
                    name: 'Tecnoar Galpão',
                    lat: -23.5226683,
                    lon: -46.5900669,
                    color: '#1d4ed8',
                    emoji: '⌂',
                    address: 'Vila Maria, São Paulo - SP, CEP 02116-000'
                }
            ];

            this.branchMarkers = [];

            branches.forEach(branch => {
                const iconHtml = `
                    <div class="tecnoar-branch-marker" style="background-color: ${branch.color};">
                        <span style="line-height:1;">${branch.emoji}</span>
                        <div class="tecnoar-branch-label">${branch.name}</div>
                    </div>
                `;
                const icon = L.divIcon({
                    html: iconHtml,
                    className: '',
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                });
                const marker = L.marker([branch.lat, branch.lon], { icon, zIndexOffset: 900 })
                    .addTo(this.map)
                    .bindPopup(`
                        <div class="p-1 min-w-[180px]">
                            <div class="text-[10px] text-gray-500 uppercase font-bold mb-1">Sede Tecnoar</div>
                            <div class="text-sm font-bold text-gray-900 mb-1">${branch.name}</div>
                            <div class="text-xs text-gray-600">${branch.address}</div>
                        </div>
                    `, { closeButton: true });

                this.branchMarkers.push(marker);
            });
        }

        updateMapTheme() {
            if (!this.map || !this._tileLayers) return;
            const { lightLayer, darkLayer, isDark } = this._tileLayers;

            if (isDark()) {
                this.map.removeLayer(lightLayer);
                darkLayer.addTo(this.map);
            } else {
                this.map.removeLayer(darkLayer);
                lightLayer.addTo(this.map);
            }
        }

        attachEvents() {


            // Flatpickr date picker
            this.datePicker = flatpickr('#filter-day', {
                locale: 'pt',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd/m/Y (D)',
                maxDate: new Date().fp_incr(60),
                defaultDate: "{{ $defaultDate }}",
                allowInput: false,
                onChange: () => this.loadData()
            });
            
            let debounceTimer;
            document.getElementById('filter-search').addEventListener('input', (e) => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    const term = e.target.value.toLowerCase();
                    this.filterMarkers(term);
                }, 300);
            });

            document.getElementById('btn-limpar-selecao').addEventListener('click', () => {
                this.selectedOsIds.clear();
                this.updateSelectionUI();
            });

            document.getElementById('btn-atribuir').addEventListener('click', () => {
                if (this.selectedOsIds.size === 0) return;
                
                const urlParams = new URLSearchParams();
                const selectedIds = Array.from(this.selectedOsIds);
                const selectedData = selectedIds.map(id => this.osData.find(o => o.numero_ordem == id)).filter(o => !!o);
                
                // Find if any selected OS already has a route
                const existingRoteiros = [...new Set(selectedData.filter(o => o.numero_roteiro).map(o => o.numero_roteiro))];
                
                const tecnico = document.getElementById('filter-technician').value;
                const carro = document.getElementById('filter-car').value;
                const dia = document.getElementById('filter-day').value;

                if (existingRoteiros.length === 1) {
                    // One unique existing route found -> EDIT mode
                    const roteiroId = existingRoteiros[0];
                    let url = "{{ route('rotas.edit', ':id') }}".replace(':id', roteiroId);
                    
                    const urlParams = new URLSearchParams();
                    // Add pending OS to the list if they are not already in this route
                    selectedData.filter(o => !o.has_route).forEach(os => {
                        urlParams.append('os[]', os.numero_ordem);
                    });
                    
                    if (tecnico) urlParams.append('tecnico', tecnico);
                    if (carro) urlParams.append('carro', carro);
                    if (dia) urlParams.append('data_visita', dia);

                    const params = urlParams.toString();
                    window.location.href = url + (params ? '?' + params : '');
                } else {
                    // No existing route OR multiple different routes -> CREATE mode
                    const urlParams = new URLSearchParams();
                    selectedIds.forEach(id => urlParams.append('os[]', id));
                    
                    if (tecnico) urlParams.append('tecnico', tecnico);
                    if (carro) urlParams.append('carro', carro);
                    if (dia) urlParams.append('data_visita', dia);
                    
                    window.location.href = "{{ route('rotas.create') }}?" + urlParams.toString();
                }
            });
        }

        async loadData() {
            this.showLoader(true, 'Buscando OS pendentes...');
            const selectedDay = document.getElementById('filter-day')._flatpickr?.selectedDates[0]
                ? document.getElementById('filter-day')._flatpickr.selectedDates[0].toISOString().slice(0, 10)
                : '';
            
            try {
                let url = "{{ route('rotas.mapa-geral-data') }}?all=true";
                if (selectedDay) {
                    url += "&data_inicio=" + selectedDay;
                    url += "&data_fim=" + selectedDay;
                }
                
                const response = await fetch(url);
                const json = await response.json();
                
                console.log(`[MAPA GERAL] OS encontradas: ${json.data ? json.data.length : 0}`);
                
                if (json.success) {
                    this.osData = json.data;
                    await this.processMapData();
                } else {
                    console.error("Failed to load map data", json);
                }
            } catch (err) {
                console.error("Error fetching map data", err);
            } finally {
                this.showLoader(false);
            }
        }

        async processMapData() {
            this.clearMarkers();
            this.techColors = {}; // Reset legend
            this.techCounts = {}; // Track counts
            let processed = 0;
            const validPoints = [];

            this.showLoader(true, 'Processando localizações...');

            // Batched rendering with async pausing to avoid freezing UI
            for (let i = 0; i < this.osData.length; i++) {
                const os = this.osData[i];
                if (!os.lat) {
                    try {
                        // Respeitando o limite de 1 requisição por segundo do Nominatim (OpenStreetMap)
                        // Adicionamos um delay se a OS não vier com coordenadas do cache do servidor
                        const geo = await this.geocode(os.endereco, os.cep, os.numero);
                        if (geo && geo.lat) {
                            os.lat = geo.lat;
                            os.lon = geo.lon;
                        }
                        
                        // Pausa para evitar bloqueio pelo provedor de mapa
                        await new Promise(r => setTimeout(r, 800));
                    } catch (e) {
                         console.warn(`[MAPA] Falha ao localizar OS ${os.numero_ordem}`);
                    }
                }
                
                processed++;
                document.getElementById('loader-text').innerText = `Processando ${processed}/${this.osData.length}... (${validPoints.length} localizadas)`;

                if (os.lat && os.lon) {
                    validPoints.push(os);
                    this.addMarker(os);
                    
                    // Count by technician or 'unplanned'
                    const key = os.has_route ? os.tecnico : 'unplanned';
                    this.techCounts[key] = (this.techCounts[key] || 0) + 1;
                    
                    // Update legend live
                    this.updateLegend();
                }
            }

            this.updateLegend();

            if (validPoints.length > 0) {
                this.fitVisibleMarkers();
            }

            this.showLoader(false);
            
            // Reapply text filter if any exists
            this.filterMarkers(document.getElementById('filter-search').value.toLowerCase());
        }

        async geocode(address, cep, numero) {
            const normCep = String(cep || '').replace(/\D/g, '');
            if (normCep) {
                const res = await fetch('{{ route("rotas.geocode-cep") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ cep: normCep, numero })
                }).then(r => r.json());
                if (res.success) return res.data;
            }
            if (address) {
                const res = await fetch('{{ route("rotas.geocode") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ address: address + ", Brasil" })
                }).then(r => r.json());
                if (res.success) return res.data;
            }
            return null;
        }

        getColorForTech(techName) {
            if (!techName) return this.defaultColor;
            
            if (!this.techColors[techName]) {
                const color = this.colorPalette[this.colorIndex % this.colorPalette.length];
                this.techColors[techName] = color;
                this.colorIndex++;
            }
            return this.techColors[techName];
        }

        addMarker(os) {
            const isUnplanned = !os.has_route;
            const color = isUnplanned ? '#6b7280' : this.getColorForTech(os.tecnico);
            const isSelected = this.selectedOsIds.has(os.numero_ordem);

            const iconHtml = `
                <div class="custom-marker ${isSelected ? 'selected' : ''}" style="background-color: ${color}; width: 22px; height: 22px;">
                    ${os.tecnico && !isUnplanned ? `<div class="marker-label">${os.tecnico.split(' ')[0]}</div>` : ''}
                </div>
            `;

            const icon = L.divIcon({
                html: iconHtml,
                className: '',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });

            const marker = L.marker([os.lat, os.lon], { icon }).addTo(this.map);
            
            let popupContent = `
                <div class="p-1 min-w-[200px]">
                    <div class="text-[10px] text-gray-500 uppercase font-bold mb-1 flex justify-between">
                        <span>OS #${os.numero_ordem}</span>
                        <a href="${'{{ route('ordens.index') }}'}?numero_ordem=${os.numero_ordem}" target="_blank" class="text-red-500 hover:text-red-700">Ver OS</a>
                    </div>
                    <div class="text-sm font-bold text-gray-900 mb-1">${os.cliente}</div>
                    <div class="text-xs text-gray-600 mb-2">${os.endereco}</div>
                    <div class="flex flex-col gap-1 mb-3">
                        <span class="text-[10px] px-2 py-0.5 bg-gray-100 rounded-md font-bold text-gray-600 w-fit">
                            Setor: ${os.setor}
                        </span>
                        <span class="text-[10px] px-2 py-0.5 rounded-md font-bold w-fit ${os.has_route ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600'}">
                            ${os.has_route ? 'Roteirizado (' + os.tecnico + ')' : 'Sem Roteiro'}
                        </span>
                        <span class="text-[10px] px-2 py-0.5 bg-amber-50 rounded-md font-bold text-amber-600 w-fit">
                            Visita: ${os.data_visita}
                        </span>
                    </div>
                </div>
            `;
            
            marker.bindPopup(popupContent, { closeButton: true });

            marker.on('click', () => {
                this.toggleSelection(os.numero_ordem);
            });

            this.markers[os.numero_ordem] = marker;
            
            // Initial visibility check
            const techKey = os.has_route ? os.tecnico : 'unplanned';
            if (this.hiddenTechs.has(techKey)) {
                this.map.removeLayer(marker);
            }
        }

        clearMarkers() {
            Object.values(this.markers).forEach(m => this.map.removeLayer(m));
            this.markers = {};
        }

        filterMarkers(term) {
            this.applyAllFilters();
        }

        toggleSelection(osNumber) {
            if (this.selectedOsIds.has(osNumber)) {
                this.selectedOsIds.delete(osNumber);
            } else {
                this.selectedOsIds.add(osNumber);
            }
            this.updateSelectionUI();
        }

        updateSelectionUI() {
            const listEl = document.getElementById('selection-list');
            const countEl = document.getElementById('selected-count-badge');
            const btnAtribuir = document.getElementById('btn-atribuir');
            const btnLimpar = document.getElementById('btn-limpar-selecao');

            countEl.innerText = this.selectedOsIds.size;
            
            if (this.selectedOsIds.size === 0) {
                listEl.innerHTML = '<p class="text-gray-400 text-xs text-center mt-8">Nenhuma OS selecionada.<br>Clique nos marcadores vermelhos no mapa.</p>';
                btnAtribuir.disabled = true;
                btnLimpar.classList.add('hidden');
            } else {
                btnAtribuir.disabled = false;
                btnLimpar.classList.remove('hidden');
                
                listEl.innerHTML = '';
                this.selectedOsIds.forEach(id => {
                    const os = this.osData.find(o => o.numero_ordem == id);
                    if (os) {
                        const div = document.createElement('div');
                        div.className = "p-2 bg-gray-50 border border-red-100 rounded-lg flex items-center justify-between group";
                        div.innerHTML = `
                            <div class="flex-1 min-w-0 pr-2">
                                <div class="text-[10px] text-gray-500 font-bold mb-0.5">OS #${os.numero_ordem}</div>
                                <div class="text-xs font-bold text-gray-800 truncate">${os.cliente}</div>
                                <div class="text-[10px] text-gray-500 truncate">${os.endereco}</div>
                            </div>
                            <button class="text-gray-400 hover:text-red-600 transition-colors cursor-pointer p-1" onclick="window.mapApp.toggleSelection('${id}')">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        `;
                        listEl.appendChild(div);
                    }
                });

                // Update button text based on selection
                const selectedData = Array.from(this.selectedOsIds).map(id => this.osData.find(o => o.numero_ordem == id)).filter(o => !!o);
                const existingRoteiros = [...new Set(selectedData.filter(o => o.numero_roteiro).map(o => o.numero_roteiro))];
                
                if (existingRoteiros.length === 1) {
                    btnAtribuir.innerHTML = `
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Editar Roteiro #${existingRoteiros[0]}
                    `;
                } else {
                    btnAtribuir.innerHTML = `
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Criar Roteiro
                    `;
                }
            }
            
            // Sync all markers styles
            this.osData.forEach(os => {
                if (os.lat && this.markers[os.numero_ordem]) {
                    const isUnplanned = !os.has_route;
                    const color = isUnplanned ? '#6b7280' : this.getColorForTech(os.tecnico);
                    const isSelected = this.selectedOsIds.has(os.numero_ordem);
                    
                    this.markers[os.numero_ordem].setIcon(L.divIcon({
                        html: `
                            <div class="custom-marker ${isSelected ? 'selected' : ''}" style="background-color: ${color}; width: 22px; height: 22px;">
                                ${os.tecnico && !isUnplanned ? `<div class="marker-label">${os.tecnico.split(' ')[0]}</div>` : ''}
                            </div>
                        `,
                        className: '', iconSize: [24, 24], iconAnchor: [12, 12]
                    }));
                }
            });
        }

        updateLegend() {
            const container = document.getElementById('legend-container');
            const unplannedCount = this.techCounts['unplanned'] || 0;
            const isUnplannedVisible = !this.hiddenTechs.has('unplanned');

            container.innerHTML = `
                <div class="pb-2 mb-1 border-b border-gray-100">
                    <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Sedes</p>
                    <div class="flex items-center gap-2 py-0.5">
                        <div class="w-3 h-3 rounded-full bg-[#dc2626] flex-shrink-0 shadow-sm flex items-center justify-center" style="font-size:8px;line-height:1;">★</div>
                        <span class="text-[11px] font-bold text-gray-600">Tecnoar Matriz</span>
                    </div>
                    <div class="flex items-center gap-2 py-0.5">
                        <div class="w-3 h-3 rounded-full bg-[#1d4ed8] flex-shrink-0 shadow-sm flex items-center justify-center" style="font-size:8px;line-height:1;">⌂</div>
                        <span class="text-[11px] font-bold text-gray-600">Tecnoar Galpão</span>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-2 py-1">
                    <div class="flex items-center gap-2 overflow-hidden flex-1">
                        <input type="checkbox" class="tech-toggle rounded border-gray-300 text-red-600 focus:ring-red-500 w-3.5 h-3.5 cursor-pointer" 
                            data-tech="unplanned" ${isUnplannedVisible ? 'checked' : ''}>
                        <div class="flex items-center gap-2 flex-1" onclick="window.mapApp.showTechDetails('unplanned')">
                            <div class="w-2.5 h-2.5 rounded-full bg-[#6b7280] flex-shrink-0 shadow-sm"></div>
                            <span class="text-[11px] font-bold text-gray-600 truncate">Sem Roteiro</span>
                        </div>
                    </div>
                    <span class="text-[10px] bg-gray-100 text-gray-500 font-black px-1.5 py-0.5 rounded-full">${unplannedCount}</span>
                </div>
            `;

            Object.entries(this.techColors).forEach(([tech, color]) => {
                if (tech) {
                    const count = this.techCounts[tech] || 0;
                    const isVisible = !this.hiddenTechs.has(tech);
                    const div = document.createElement('div');
                    div.className = "flex items-center justify-between gap-2 py-1 group cursor-pointer hover:bg-gray-50 rounded-lg px-1 transition-colors";
                    div.innerHTML = `
                        <div class="flex items-center gap-2 overflow-hidden flex-1">
                            <input type="checkbox" class="tech-toggle rounded border-gray-300 text-red-600 focus:ring-red-500 w-3.5 h-3.5 cursor-pointer" 
                                data-tech="${tech}" ${isVisible ? 'checked' : ''}>
                            <div class="flex items-center gap-2 flex-1" onclick="window.mapApp.showTechDetails('${tech}')">
                                <div class="w-2.5 h-2.5 rounded-full flex-shrink-0 shadow-sm" style="background-color: ${color}"></div>
                                <span class="text-[11px] font-bold text-gray-600 truncate" title="${tech}">${tech}</span>
                            </div>
                        </div>
                        <span class="text-[10px] bg-red-100 text-red-600 font-black px-1.5 py-0.5 rounded-full">${count}</span>
                    `;
                    container.appendChild(div);
                }
            });

            // Attach toggle events
            container.querySelectorAll('.tech-toggle').forEach(chk => {
                chk.addEventListener('change', (e) => {
                    const tech = e.target.dataset.tech;
                    if (e.target.checked) {
                        this.hiddenTechs.delete(tech);
                    } else {
                        this.hiddenTechs.add(tech);
                    }
                    this.applyAllFilters();
                });
            });
        }

        applyAllFilters() {
            const searchTerm = document.getElementById('filter-search').value.toLowerCase();
            
            this.osData.forEach(os => {
                const marker = this.markers[os.numero_ordem];
                if (!marker) return;

                const techKey = os.has_route ? os.tecnico : 'unplanned';
                const isTechVisible = !this.hiddenTechs.has(techKey);
                
                const matchSearch = searchTerm === '' || 
                    os.cliente.toLowerCase().includes(searchTerm) || 
                    os.endereco.toLowerCase().includes(searchTerm) || 
                    String(os.numero_ordem).includes(searchTerm);

                if (isTechVisible && matchSearch && os.lat) {
                    if (!this.map.hasLayer(marker)) marker.addTo(this.map);
                } else {
                    if (this.map.hasLayer(marker)) this.map.removeLayer(marker);
                }
            });

            this.fitVisibleMarkers();
        }

        fitVisibleMarkers() {
            if (!this.map) return;
            
            // Re-invalidate size immediately
            this.map.invalidateSize();
            
            // Give the browser a moment to settle layout/animations
            setTimeout(() => {
                this.map.invalidateSize();
                
                const visibleMarkers = [];
                
                // OS Markers
                Object.values(this.markers).forEach(marker => {
                    if (this.map.hasLayer(marker)) {
                        visibleMarkers.push(marker);
                    }
                });

                // Branch Markers
                if (this.branchMarkers) {
                    this.branchMarkers.forEach(marker => {
                        if (this.map.hasLayer(marker)) {
                            visibleMarkers.push(marker);
                        }
                    });
                }

                if (visibleMarkers.length > 0) {
                    const group = new L.featureGroup(visibleMarkers);
                    this.map.fitBounds(group.getBounds(), { 
                        padding: [120, 120], // Even more padding to prevent cutting labels
                        animate: true,
                        maxZoom: 16
                    });
                }
            }, 250);
        }

        showTechDetails(techName) {
            const techData = this.osData.filter(os => (os.has_route ? os.tecnico : 'unplanned') === (techName === 'unplanned' ? 'unplanned' : techName));
            
            const card = document.getElementById('tech-details-card');
            const nameEl = document.getElementById('tech-details-name');
            const listEl = document.getElementById('tech-os-list');
            
            nameEl.innerText = techName === 'unplanned' ? 'Sem Roteiro' : `Roteiro: ${techName}`;
            card.classList.remove('hidden');
            
            listEl.innerHTML = techData.map(os => {
                let checklistHtml = '';
                if (os.checklist) {
                    checklistHtml = `
                        <div class="mt-2 p-2 bg-gray-50/50 rounded-lg border border-gray-100 text-[10px]">
                            <p class="font-black text-gray-400 uppercase mb-1 flex items-center gap-1">
                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                                ITENS CONFERIDOS
                            </p>
                            <div class="space-y-1">
                                ${Object.entries(os.checklist).map(([key, value]) => `
                                    <div class="flex items-center gap-2">
                                        <div class="w-1.5 h-1.5 rounded-full ${value ? 'bg-emerald-500' : 'bg-gray-300'}"></div>
                                        <span class="${value ? 'text-gray-700 font-bold' : 'text-gray-400'}">${key}</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                }

                return `
                    <div class="p-3 bg-white border border-gray-100 rounded-xl shadow-sm hover:border-red-100 transition-all">
                        <div class="flex justify-between items-start mb-1">
                            <span class="text-[10px] font-black text-red-600">OS #${os.numero_ordem}</span>
                            <span class="text-[9px] px-1.5 py-0.5 bg-amber-50 text-amber-600 rounded font-bold uppercase">${os.data_visita}</span>
                        </div>
                        <div class="text-xs font-bold text-gray-800 mb-1 truncate" title="${os.cliente}">${os.cliente}</div>
                        <div class="text-[10px] text-gray-500 mb-2 truncate">${os.endereco}</div>
                        <div class="flex flex-wrap gap-1">
                             <span class="text-[9px] px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded font-bold uppercase">${os.setor}</span>
                        </div>
                        ${checklistHtml}
                    </div>
                `;
            }).join('');
            
            // Highlight markers for this technician on map
            Object.keys(this.markers).forEach(id => {
               const os = this.osData.find(o => o.numero_ordem == id);
               if (os) {
                   const osTech = os.has_route ? os.tecnico : 'unplanned';
                   const marker = this.markers[id];
                   if (osTech === techName) {
                       marker.getElement()?.classList.add('pulse-marker');
                   } else {
                       marker.getElement()?.classList.remove('pulse-marker');
                   }
               }
            });
        }

        closeTechDetails() {
            document.getElementById('tech-details-card').classList.add('hidden');
            Object.values(this.markers).forEach(m => {
                m.getElement()?.classList.remove('pulse-marker');
            });
        }

        showLoader(show, text = 'Carregando...') {
            const loader = document.getElementById('map-loader');
            const textEl = document.getElementById('loader-text');
            textEl.innerText = text;
            if (show) {
                loader.classList.remove('hidden');
            } else {
                loader.classList.add('hidden');
            }
        }
    }

    // Initialize map
    document.addEventListener('DOMContentLoaded', () => {
        window.mapApp = new GlobalOSMap();
    });
</script>
@endpush
@endsection
