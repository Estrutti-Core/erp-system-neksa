<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\RouteOptimizationService;
use App\Models\ServiceOrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RouteController extends Controller
{
    public function __construct(
        private readonly RouteOptimizationService $optimizationService
    ) {}

    public function index(Request $request): View
    {
        $routes = Route::with(['technician', 'serviceOrders'])
            ->when($request->date, fn ($q, $d) => $q->whereDate('route_date', $d))
            ->when($request->technician_id, fn ($q, $t) => $q->where('technician_id', $t))
            ->orderBy('route_date', 'desc')
            ->paginate(15)
            ->withQueryString();

        $technicians = User::role('technician')->orderBy('name')->get();

        // OS sem rota para o dia selecionado (painel de montagem)
        $date = $request->date ?? today()->toDateString();
        $availableOrders = ServiceOrder::with(['client', 'clientAddress'])
            ->whereDate('scheduled_at', $date)
            ->whereHas('status', fn ($q) => $q->where('is_completed_state', false)->where('is_cancelled_state', false))
            ->whereDoesntHave('routeServiceOrders')
            ->get();

        $availableOrdersJson = $availableOrders->map(fn($os) => [
            'id'   => $os->id,
            'code' => $os->code,
            'lat'  => $os->clientAddress?->latitude,
            'lng'  => $os->clientAddress?->longitude,
            'client' => $os->client->name,
            'address' => $os->clientAddress?->short_address ?? '',
        ]);

        return view('routes.index', compact('routes', 'technicians', 'availableOrders', 'availableOrdersJson', 'date'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'technician_id'      => ['required', 'exists:users,id'],
            'route_date'         => ['required', 'date'],
            'service_order_ids'  => ['required', 'array', 'min:1'],
            'service_order_ids.*'=> ['exists:service_orders,id'],
            'origin_lat'         => ['nullable', 'numeric'],
            'origin_lng'         => ['nullable', 'numeric'],
        ]);

        $serviceOrders = ServiceOrder::with(['clientAddress', 'client'])
            ->whereIn('id', $request->service_order_ids)
            ->get();

        $originLat = $request->origin_lat ?? -23.5505;
        $originLng = $request->origin_lng ?? -46.6333;

        $optimized = $this->optimizationService->optimize($serviceOrders, $originLat, $originLng);

        $route = Route::create([
            'technician_id'     => $request->technician_id,
            'created_by'        => auth()->id(),
            'route_date'        => $request->route_date,
            'total_distance_km' => $optimized['total_distance_km'],
            'estimated_minutes' => $optimized['estimated_minutes'],
            'optimized_order'   => $optimized['order'],
        ]);

        foreach ($optimized['waypoints'] as $idx => $waypoint) {
            $route->serviceOrders()->attach($waypoint['service_order_id'], [
                'sequence'                    => $idx + 1,
                'distance_from_prev_km'       => $waypoint['distance_from_prev_km'],
                'estimated_minutes_from_prev' => $waypoint['estimated_minutes_from_prev'],
            ]);
        }

        return redirect()->route('routes.show', $route)
            ->with('success', 'Roteiro criado e otimizado com sucesso!');
    }

    public function show(Route $route): View
    {
        $route->load(['technician', 'routeServiceOrders.serviceOrder.client.addresses']);

        $waypointsJson = $route->routeServiceOrders->map(function ($rso) {
            $address = $rso->serviceOrder->clientAddress;

            return [
                'id'       => $rso->serviceOrder->id,
                'code'     => $rso->serviceOrder->code,
                'sequence' => $rso->sequence,
                'client'   => $rso->serviceOrder->client->name,
                'address'  => $address?->short_address ?? '',
                'lat'      => $address?->latitude,
                'lng'      => $address?->longitude,
                'status'   => $rso->serviceOrder->status->slug,
                'status_label' => $rso->serviceOrder->status->name,
                'distance' => $rso->distance_from_prev_km,
                'eta'      => $rso->estimated_arrival_at?->format('H:i'),
            ];
        });

        return view('routes.show', compact('route', 'waypointsJson'));
    }
}
