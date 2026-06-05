<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrderStatus;
use App\Http\Requests\StoreServiceOrderRequest;
use App\Http\Requests\UpdateServiceOrderRequest;
use App\Models\Client;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\FiscalSummaryService;
use App\Services\ServiceOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceOrderController extends Controller
{
    public function __construct(
        private readonly ServiceOrderService $serviceOrderService,
        private readonly FiscalSummaryService $fiscalSummaryService,
    ) {
        $this->authorizeResource(ServiceOrder::class, 'serviceOrder');
    }

    public function index(Request $request): View
    {
        $user = auth()->user();

        $orders = ServiceOrder::query()
            ->with(['client', 'technician', 'clientAddress'])
            ->when($user->isTechnician(), fn ($q) => $q->forTechnician($user->id))
            ->when($request->status, fn ($q, $s) => $q->status($s))
            ->when($request->priority, fn ($q, $p) => $q->where('priority', $p))
            ->when($request->technician_id, fn ($q, $t) => $q->where('technician_id', $t))
            ->when($request->search, fn ($q, $s) => $q->search($s))
            ->when($request->date, fn ($q, $d) => $q->whereDate('scheduled_at', $d))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $technicians = User::role('technician')->orderBy('name')->get();
        $statuses    = ServiceOrderStatus::pluck('name', 'slug')->toArray();

        return view('service-orders.index', compact('orders', 'technicians', 'statuses'));
    }

    public function create(): View
    {
        $clients     = Client::active()->orderBy('name')->get();
        $technicians = User::role('technician')->orderBy('name')->get();
        $priorities  = \App\Enums\ServiceOrderPriority::options();

        return view('service-orders.create', compact('clients', 'technicians', 'priorities'));
    }

    public function store(StoreServiceOrderRequest $request): RedirectResponse
    {
        $serviceOrder = $this->serviceOrderService->create(
            $request->validated(),
            auth()->user()
        );

        return redirect()->route('service-orders.show', $serviceOrder)
            ->with('success', "OS {$serviceOrder->code} criada com sucesso!");
    }

    public function show(ServiceOrder $serviceOrder): View
    {
        $serviceOrder->load([
            'client',
            'clientAddress',
            'equipment',
            'technician',
            'creator',
            'items.service',
            'attachments.uploader',
            'checklists.template',
            'checklists.instancedQuestions.answer',
            'checkins.user',
            'history.user',
            'signature',
        ]);

        $technicians     = User::role('technician')->orderBy('name')->get();
        $allowedStatuses = $serviceOrder->status->allowedTransitions()->get();

        return view('service-orders.show', compact('serviceOrder', 'technicians', 'allowedStatuses'));
    }

    public function edit(ServiceOrder $serviceOrder): View
    {
        $serviceOrder->load(['client', 'clientAddress', 'items']);
        $clients     = Client::active()->orderBy('name')->get();
        $technicians = User::role('technician')->orderBy('name')->get();

        return view('service-orders.edit', compact('serviceOrder', 'clients', 'technicians'));
    }

    public function update(UpdateServiceOrderRequest $request, ServiceOrder $serviceOrder): RedirectResponse
    {
        $this->serviceOrderService->update($serviceOrder, $request->validated(), auth()->user());

        return redirect()->route('service-orders.show', $serviceOrder)
            ->with('success', 'OS atualizada com sucesso!');
    }

    public function destroy(ServiceOrder $serviceOrder): RedirectResponse
    {
        $serviceOrder->delete();

        return redirect()->route('service-orders.index')
            ->with('success', "OS {$serviceOrder->code} removida.");
    }

    /**
     * Duplica uma OS existente.
     */
    public function duplicate(ServiceOrder $serviceOrder): RedirectResponse
    {
        $this->authorize('create', ServiceOrder::class);

        $newOrder = $this->serviceOrderService->duplicate($serviceOrder, auth()->user());

        return redirect()->route('service-orders.show', $newOrder)
            ->with('success', "OS {$newOrder->code} criada por duplicação com sucesso!");
    }

    /**
     * Altera o status da OS.
     */
    public function changeStatus(Request $request, ServiceOrder $serviceOrder): RedirectResponse
    {
        $this->authorize('changeStatus', $serviceOrder);

        $request->validate([
            'status' => ['required', 'string', 'exists:service_order_statuses,slug'],
            'note'   => ['nullable', 'string'],
        ]);

        $newStatus = ServiceOrderStatus::where('slug', $request->status)->firstOrFail();

        $this->serviceOrderService->changeStatus(
            $serviceOrder,
            $newStatus,
            auth()->user(),
            $request->note,
        );

        return back()->with('success', 'Status da OS atualizado!');
    }

    /**
     * Gera o PDF da OS.
     */
    public function pdf(ServiceOrder $serviceOrder): \Illuminate\Http\Response
    {
        $this->authorize('generatePdf', $serviceOrder);

        return $this->fiscalSummaryService->generatePdf($serviceOrder);
    }

    /**
     * Resumo fiscal da OS.
     */
    public function fiscal(ServiceOrder $serviceOrder): View
    {
        $this->authorize('view', $serviceOrder);

        $fiscal = $this->fiscalSummaryService->generate($serviceOrder);

        return view('fiscal.show', compact('serviceOrder', 'fiscal'));
    }
}
