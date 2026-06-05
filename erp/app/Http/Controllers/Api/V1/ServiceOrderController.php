<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ServiceOrderResource;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderStatus;
use App\Services\ServiceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceOrderController extends Controller
{
    public function __construct(
        private readonly ServiceOrderService $serviceOrderService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $orders = ServiceOrder::query()
            ->with(['client', 'clientAddress', 'technician'])
            ->when($user->isTechnician(), fn ($q) => $q->forTechnician($user->id))
            ->when($request->status, fn ($q, $s) => $q->status($s))
            ->latest()
            ->paginate(20);

        return ServiceOrderResource::collection($orders);
    }

    public function show(ServiceOrder $serviceOrder): ServiceOrderResource
    {
        $this->authorize('view', $serviceOrder);
        $serviceOrder->load(['client', 'clientAddress', 'technician', 'items', 'photos', 'history', 'signature']);

        return new ServiceOrderResource($serviceOrder);
    }

    public function updateStatus(Request $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $this->authorize('changeStatus', $serviceOrder);

        $request->validate([
            'status' => ['required', 'string', 'exists:service_order_statuses,slug'],
            'note'   => ['nullable', 'string'],
        ]);

        $newStatus = ServiceOrderStatus::where('slug', $request->status)->firstOrFail();

        $updated = $this->serviceOrderService->changeStatus(
            $serviceOrder,
            $newStatus,
            $request->user(),
            $request->note,
        );

        return response()->json([
            'message'       => 'Status atualizado com sucesso.',
            'service_order' => new ServiceOrderResource($updated),
        ]);
    }

    public function checkIn(Request $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $this->authorize('view', $serviceOrder);

        $request->validate([
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $updated = $this->serviceOrderService->checkIn(
            $serviceOrder,
            $request->latitude,
            $request->longitude,
            $request->user(),
        );

        return response()->json([
            'message'       => 'Check-in registrado com sucesso.',
            'service_order' => new ServiceOrderResource($updated),
        ]);
    }
}
