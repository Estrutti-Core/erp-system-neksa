@extends('layouts.app')
@section('title', 'Roteirização')
@section('topbar-actions')
    <span class="text-sm text-muted">Data: <strong>{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</strong></span>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:340px 1fr;gap:20px;height:calc(100vh - 140px)">

    {{-- Painel lateral --}}
    <div style="overflow-y:auto;display:flex;flex-direction:column;gap:16px">

        {{-- Filtros --}}
        <div class="card">
            <form method="GET" class="flex flex-wrap gap-2">
                <div class="form-group" style="margin:0;flex:1">
                    <input type="date" name="date" value="{{ $date }}" class="form-control">
                </div>
                <div class="form-group" style="margin:0;flex:1">
                    <select name="technician_id" class="form-control">
                        <option value="">Técnico</option>
                        @foreach($technicians as $t)
                            <option value="{{ $t->id }}" {{ request('technician_id') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            </form>
        </div>

        {{-- OS disponíveis para roteirizar --}}
        @if($availableOrders->isNotEmpty())
        <div class="card">
            <h3 class="font-bold mb-3 flex items-center gap-2" style="font-size:14px"><x-heroicon-o-clipboard-document-list class="w-5 h-5 text-indigo-600"/> OS sem Roteiro ({{ $availableOrders->count() }})</h3>
            <form method="POST" action="{{ route('routes.store') }}" id="routeForm">
                @csrf
                <input type="hidden" name="route_date" value="{{ $date }}">
                <input type="hidden" name="origin_lat" id="originLat" value="-23.5505">
                <input type="hidden" name="origin_lng" id="originLng" value="-46.6333">

                <div class="form-group">
                    <select name="technician_id" class="form-control" required>
                        <option value="">Selecione o técnico</option>
                        @foreach($technicians as $t)
                            <option value="{{ $t->id }}" {{ request('technician_id') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="max-height:280px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;padding:8px">
                    @foreach($availableOrders as $os)
                    <label style="display:flex;align-items:flex-start;gap:8px;padding:8px;border-radius:6px;cursor:pointer;transition:background .1s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                        <input type="checkbox" name="service_order_ids[]" value="{{ $os->id }}" style="margin-top:2px;accent-color:var(--primary)">
                        <div>
                            <div style="font-size:13px;font-weight:600">{{ $os->code }}</div>
                            <div style="font-size:12px;color:#64748b">{{ $os->client->name }}</div>
                            <div style="font-size:11px;color:#94a3b8">{{ $os->clientAddress?->short_address ?? 'Sem endereço' }}</div>
                        </div>
                    </label>
                    @endforeach
                </div>

                <button type="button" onclick="useMyLocation()" class="btn btn-secondary btn-sm w-full mt-2" style="justify-content:center">
                    Usar minha localização como origem
                </button>

                <button type="submit" class="btn btn-primary w-full mt-2" style="justify-content:center">
                    Otimizar e Criar Roteiro
                </button>
            </form>
        </div>
        @endif

        {{-- Roteiros do dia --}}
        @if($routes->isNotEmpty())
        <div class="card">
            <h3 class="font-bold mb-3 flex items-center gap-2" style="font-size:14px"><x-heroicon-o-calendar class="w-5 h-5 text-indigo-600"/> Roteiros do Período</h3>
            @foreach($routes as $route)
            <a href="{{ route('routes.show', $route) }}" style="display:block;text-decoration:none;color:inherit;border-bottom:1px solid #f1f5f9;padding:10px 0">
                <div class="flex justify-between items-center">
                    <div>
                        <div style="font-weight:600;font-size:13px">{{ $route->technician->name }}</div>
                        <div style="font-size:11px;color:#64748b">{{ $route->route_date->format('d/m/Y') }} · {{ $route->serviceOrders->count() }} OS</div>
                    </div>
                    <div style="font-size:12px;color:#64748b;text-align:right">
                        @if($route->total_distance_km) {{ $route->total_distance_km }} km @endif
                        @if($route->estimated_minutes) <br>~{{ $route->estimated_duration }} @endif
                    </div>
                </div>
            </a>
            @endforeach
        </div>
        @endif

    </div>

    {{-- Mapa --}}
    <div style="border-radius:14px;overflow:hidden;border:1px solid #e2e8f0">
        <div id="map" style="width:100%;height:100%"></div>
    </div>
</div>

@push('scripts')
<script>
const map = L.map('map').setView([-23.5505, -46.6333], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

// Plotar OS disponíveis
const orders = @json($availableOrdersJson);

orders.forEach(os => {
    if (!os.lat || !os.lng) return;
    L.marker([os.lat, os.lng], {
        icon: L.divIcon({
            className: '',
            html: `<div style="background:#4f46e5;color:#fff;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;box-shadow:0 2px 8px rgba(0,0,0,.3)">OS</div>`,
            iconSize: [32, 32], iconAnchor: [16, 16]
        })
    }).addTo(map).bindPopup(`<strong>${os.code}</strong><br>${os.client}<br><small>${os.address}</small>`);
});

function useMyLocation() {
    navigator.geolocation.getCurrentPosition(pos => {
        document.getElementById('originLat').value = pos.coords.latitude;
        document.getElementById('originLng').value = pos.coords.longitude;
        L.marker([pos.coords.latitude, pos.coords.longitude], {
            icon: L.divIcon({
                className: '',
                html: `<div style="background:#16a34a;color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:bold">Origem</div>`,
                iconSize: [28, 28], iconAnchor: [14, 14]
            })
        }).addTo(map).bindPopup('Ponto de origem').openPopup();
        map.setView([pos.coords.latitude, pos.coords.longitude], 13);
    }, () => alert('Não foi possível obter sua localização.'));
}
</script>
@endpush
@endsection
