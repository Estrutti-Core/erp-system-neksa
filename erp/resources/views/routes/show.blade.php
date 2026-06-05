@extends('layouts.app')
@section('title', 'Roteiro — ' . $route->technician->name)

@section('content')
<div class="flex items-center gap-3 mb-4">
    <a href="{{ route('routes.index') }}" class="btn btn-secondary btn-sm">← Voltar</a>
    <div>
        <span class="font-bold">{{ $route->technician->name }}</span>
        <span class="text-muted text-sm ml-2">{{ $route->route_date->format('d/m/Y') }}</span>
    </div>
    <div class="flex gap-3 ml-auto text-sm">
        @if($route->total_distance_km)<span class="badge badge-blue">{{ $route->total_distance_km }} km</span>@endif
        @if($route->estimated_minutes)<span class="badge badge-amber">{{ $route->estimated_duration }}</span>@endif
        <span class="badge badge-slate">{{ $route->routeServiceOrders->count() }} paradas</span>
    </div>
</div>

<div style="display:grid;grid-template-columns:320px 1fr;gap:20px;height:calc(100vh - 160px)">
    {{-- Lista de paradas --}}
    <div style="overflow-y:auto">
        <div class="card">
            <h3 class="font-bold mb-3 flex items-center gap-2" style="font-size:14px"><x-heroicon-o-map-pin class="w-5 h-5 text-indigo-600"/> Sequência Otimizada</h3>
            @foreach($route->routeServiceOrders as $idx => $rso)
            <a href="{{ route('service-orders.show', $rso->serviceOrder) }}" style="display:flex;gap:12px;align-items:flex-start;text-decoration:none;color:inherit;padding:10px 0;border-bottom:1px solid #f1f5f9">
                <div style="width:28px;height:28px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0">{{ $rso->sequence }}</div>
                <div style="flex:1;min-width:0">
                    <div style="font-weight:600;font-size:13px">{{ $rso->serviceOrder->client->name }}</div>
                    <div style="font-size:11px;color:#64748b;margin-top:2px">{{ $rso->serviceOrder->code }}</div>
                    <div style="font-size:11px;color:#94a3b8">{{ $rso->serviceOrder->clientAddress?->short_address ?? '—' }}</div>
                    @if($rso->distance_from_prev_km && $idx > 0)
                    <div style="font-size:11px;color:#4f46e5;margin-top:3px">{{ $rso->distance_from_prev_km }} km · {{ $rso->estimated_minutes_from_prev ?? '—' }}min</div>
                    @endif
                </div>
                <span class="badge badge-{{ $rso->serviceOrder->status->color }}">{{ $rso->serviceOrder->status->name }}</span>
            </a>
            @endforeach
        </div>
    </div>

    {{-- Mapa --}}
    <div style="border-radius:14px;overflow:hidden;border:1px solid #e2e8f0">
        <div id="map" style="width:100%;height:100%"></div>
    </div>
</div>

@push('scripts')
<script>
const waypoints = @json($waypointsJson);
const map = L.map('map');
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution:'© OpenStreetMap'}).addTo(map);

const validPoints = waypoints.filter(w => w.lat && w.lng);
if (!validPoints.length) { map.setView([-23.5505,-46.6333],12); }
else {
    const latlngs = validPoints.map(w => [w.lat, w.lng]);
    L.polyline(latlngs, {color:'#4f46e5', weight:3, dashArray:'6 4', opacity:.8}).addTo(map);

    validPoints.forEach((w, i) => {
        const color = w.status === 'completed' ? '#16a34a' : w.status === 'cancelled' ? '#dc2626' : '#4f46e5';
        L.marker([w.lat, w.lng], {
            icon: L.divIcon({
                className: '',
                html: `<div style="background:${color};color:#fff;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;box-shadow:0 2px 8px rgba(0,0,0,.3);border:2px solid #fff">${w.sequence}</div>`,
                iconSize:[30,30], iconAnchor:[15,15]
            })
        }).addTo(map).bindPopup(`<strong>${w.sequence}. ${w.client}</strong><br>${w.code}<br><small>${w.address}</small><br><em>${w.status_label}</em>`);
    });
    map.fitBounds(L.latLngBounds(latlngs).pad(.1));
}
</script>
@endpush
@endsection
