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
use App\Services\ExportXlsxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

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

    public function exportXlsx(Request $request)
    {
        $user = auth()->user();

        $query = ServiceOrder::query()
            ->with(['client', 'technician', 'clientAddress'])
            ->when($user->isTechnician(), fn ($q) => $q->forTechnician($user->id))
            ->when($request->status, fn ($q, $s) => $q->status($s))
            ->when($request->priority, fn ($q, $p) => $q->where('priority', $p))
            ->when($request->technician_id, fn ($q, $t) => $q->where('technician_id', $t))
            ->when($request->search, fn ($q, $s) => $q->search($s))
            ->when($request->date, fn ($q, $d) => $q->whereDate('scheduled_at', $d));

        $query->latest();

        $headers = ['Código', 'Cliente', 'Técnico', 'Agendado', 'Prioridade', 'Status', 'Valor Total'];
        $formats = [
            'D' => 'date',
            'G' => 'currency',
        ];

        return app(ExportXlsxService::class)->export(
            'Ordens de Serviço',
            $headers,
            $query,
            function ($order) {
                return [
                    $order->code,
                    $order->client->name ?? 'N/A',
                    $order->technician->name ?? 'N/A',
                    $order->scheduled_at ? $order->scheduled_at->format('Y-m-d H:i') : '',
                    $order->priority->value ?? $order->priority,
                    $order->status->name ?? $order->status_slug,
                    (float) $order->total_amount,
                ];
            },
            $formats,
            'ServiceOrders.xlsx'
        );
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
            'payments',
            'receivable.installments',
        ]);

        $clients         = Client::active()->orderBy('name')->get();
        $technicians     = User::role('technician')->orderBy('name')->get();
        $allowedStatuses = $serviceOrder->status->allowedTransitions()->get();
        $accounts        = \App\Models\FinancialAccount::where('is_active', true)->get();
        $paymentMethods  = \App\Enums\PaymentMethod::cases();

        return view('service-orders.show', compact('serviceOrder', 'clients', 'technicians', 'allowedStatuses', 'accounts', 'paymentMethods'));
    }

    public function edit(ServiceOrder $serviceOrder): RedirectResponse
    {
        return redirect()->route('service-orders.show', [$serviceOrder, 'edit' => 1]);
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
     * Exibe o formulário de pagamento para conclusão da OS.
     */
    public function paymentForm(Request $request, ServiceOrder $serviceOrder): View
    {
        $this->authorize('changeStatus', $serviceOrder);

        $targetStatus = $request->query('target_status');
        $newStatus = ServiceOrderStatus::where('slug', $targetStatus)->firstOrFail();

        $accounts = \App\Models\FinancialAccount::where('is_active', true)->get();
        $methods = \App\Enums\PaymentMethod::cases();
        $paymentConditions = \App\Models\PaymentCondition::where('is_active', true)->get();

        return view('service-orders.payment', compact('serviceOrder', 'newStatus', 'accounts', 'methods', 'paymentConditions'));
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

        // Se for transição para concluído e não vier com pagamentos nem installments no request, redireciona para a tela de pagamento
        if ($newStatus->is_completed_state && !$request->has('payments') && !$request->has('installments')) {
            return redirect()->route('service-orders.payment', [
                'serviceOrder' => $serviceOrder->id,
                'target_status' => $newStatus->slug,
            ]);
        }

        // Se vier com installments (parcelas customizadas), faz a validação e processamento
        if ($newStatus->is_completed_state && $request->has('installments')) {
            $validated = $request->validate([
                'installments' => 'required|array|min:1',
                'installments.*.due_date' => 'required|date',
                'installments.*.amount' => 'required|numeric|min:0.01',
                'installments.*.payment_method' => 'required|string',
                'installments.*.financial_account_id' => 'required|exists:financial_accounts,id',
            ], [
                'installments.required' => 'Informe pelo menos uma parcela.',
                'installments.*.amount.required' => 'O valor da parcela é obrigatório.',
                'installments.*.due_date.required' => 'A data de vencimento da parcela é obrigatória.',
                'installments.*.financial_account_id.required' => 'Selecione a conta financeira de destino da parcela.',
                'installments.*.payment_method.required' => 'Selecione a forma de pagamento da parcela.',
            ]);

            // Valida se a soma dos valores informados bate com o total da OS
            $totalPayments = array_sum(array_column($validated['installments'], 'amount'));
            if (abs($totalPayments - (float) $serviceOrder->total_amount) > 0.01) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', sprintf('A soma das parcelas (R$ %s) deve ser exatamente igual ao valor total da OS (R$ %s).', number_format($totalPayments, 2, ',', '.'), number_format((float) $serviceOrder->total_amount, 2, ',', '.')));
            }

            // Grava os pagamentos de forma atômica antes de trocar o status
            DB::transaction(function () use ($serviceOrder, $validated) {
                $serviceOrder->payments()->delete();
                foreach ($validated['installments'] as $inst) {
                    $serviceOrder->payments()->create([
                        'payment_method'       => $inst['payment_method'],
                        'amount'               => $inst['amount'],
                        'installments_count'   => 1,
                        'first_due_date'       => $inst['due_date'],
                        'financial_account_id' => $inst['financial_account_id'],
                    ]);
                }
            });
        } elseif ($newStatus->is_completed_state && $request->has('payments')) {
            $validated = $request->validate([
                'payments' => 'required|array|min:1',
                'payments.*.payment_method' => 'required|string',
                'payments.*.amount' => 'required|numeric|min:0.01',
                'payments.*.installments_count' => 'required|integer|min:1',
                'payments.*.first_due_date' => 'required|date',
                'payments.*.financial_account_id' => 'required|exists:financial_accounts,id',
            ], [
                'payments.required' => 'Informe pelo menos uma forma de pagamento.',
                'payments.*.amount.required' => 'O valor do pagamento é obrigatório.',
                'payments.*.first_due_date.required' => 'A data de vencimento da primeira parcela é obrigatória.',
                'payments.*.financial_account_id.required' => 'Selecione a conta financeira de destino.',
            ]);

            // Valida se a soma dos valores informados bate com o total da OS
            $totalPayments = array_sum(array_column($validated['payments'], 'amount'));
            if (abs($totalPayments - (float) $serviceOrder->total_amount) > 0.01) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', sprintf('A soma dos pagamentos (R$ %s) deve ser exatamente igual ao valor total da OS (R$ %s).', number_format($totalPayments, 2, ',', '.'), number_format((float) $serviceOrder->total_amount, 2, ',', '.')));
            }

            // Grava os pagamentos de forma atômica antes de trocar o status
            DB::transaction(function () use ($serviceOrder, $validated) {
                $serviceOrder->payments()->delete();
                foreach ($validated['payments'] as $paymentData) {
                    $serviceOrder->payments()->create([
                        'payment_method'       => $paymentData['payment_method'],
                        'amount'               => $paymentData['amount'],
                        'installments_count'   => $paymentData['installments_count'],
                        'first_due_date'       => $paymentData['first_due_date'],
                        'financial_account_id' => $paymentData['financial_account_id'],
                    ]);
                }
            });
        }

        $this->serviceOrderService->changeStatus(
            $serviceOrder,
            $newStatus,
            auth()->user(),
            $request->note,
        );

        return redirect()->route('service-orders.show', $serviceOrder)
            ->with('success', 'Status da OS atualizado com sucesso!');
    }

    public function pdf(ServiceOrder $serviceOrder, Request $request): \Illuminate\Http\Response
    {
        $this->authorize('generatePdf', $serviceOrder);

        return $this->fiscalSummaryService->generatePdf($serviceOrder, $request->query('mode', 'technical'));
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
