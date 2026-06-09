<?php

namespace App\Http\Controllers;

use App\Models\Payable;
use App\Models\PayableInstallment;
use App\Models\Supplier;
use App\Models\Company;
use App\Services\FinancialService;
use App\Services\ExportXlsxService;
use App\Enums\PaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\InstallmentStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayableController extends Controller
{
    public function __construct(
        private readonly FinancialService $financialService
    ) {
        $this->authorizeResource(Payable::class, 'payable');
    }

    public function index(Request $request): View
    {
        $query = Payable::query()->with(['supplier', 'installments']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%")
                  ->orWhereHas('supplier', function($qc) use ($search) {
                      $qc->where('name', 'ilike', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('competence_date', [$request->input('start_date'), $request->input('end_date')]);
        }

        $payables = $query->latest()->paginate(15)->withQueryString();
        $statuses = PaymentStatus::cases();

        return view('payables.index', compact('payables', 'statuses'));
    }

    public function exportXlsx(Request $request)
    {
        $query = Payable::query()->with(['supplier']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%")
                  ->orWhereHas('supplier', function($qc) use ($search) {
                      $qc->where('name', 'ilike', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('competence_date', [$request->input('start_date'), $request->input('end_date')]);
        }

        $query->orderBy('competence_date', 'desc');

        $headers = ['Código', 'Fornecedor', 'Descrição', 'Valor Total', 'Status', 'Competência'];
        $formats = [
            'D' => 'currency',
            'F' => 'date',
        ];

        return app(ExportXlsxService::class)->export(
            'Pagáveis',
            $headers,
            $query,
            function ($payable) {
                return [
                    $payable->code,
                    $payable->supplier->name ?? 'Fornecedor Avulso',
                    $payable->description,
                    (float) $payable->total_amount,
                    $payable->status->label() ?? $payable->status,
                    $payable->competence_date ? $payable->competence_date->format('Y-m-d') : '',
                ];
            },
            $formats,
            'Payables.xlsx'
        );
    }

    public function show(Payable $payable): View
    {
        $payable->load(['supplier', 'installments', 'events.user']);
        $paymentMethods = PaymentMethod::cases();
        return view('payables.show', compact('payable', 'paymentMethods'));
    }

    public function create(): View
    {
        $suppliers = Supplier::orderBy('name')->get();
        return view('payables.create', compact('suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'description' => 'required|string|max:255',
            'total_amount' => 'required|numeric|min:0.01',
            'competence_date' => 'required|date',
            'notes' => 'nullable|string',
            'installments' => 'required|array|min:1',
            'installments.*.due_date' => 'required|date',
            'installments.*.amount' => 'required|numeric|min:0.01',
        ]);

        $installmentsData = $request->input('installments');
        $sum = array_sum(array_column($installmentsData, 'amount'));

        if (abs($sum - (float) $request->input('total_amount')) > 0.01) {
            throw ValidationException::withMessages([
                'total_amount' => 'A soma das parcelas deve ser igual ao valor total do título.',
            ]);
        }

        $company = Company::first();
        if (!$company) {
            $company = Company::create([
                'id' => 1,
                'name' => 'Neksa ERP',
            ]);
        }

        $this->financialService->createPayable([
            'company_id' => $company->id,
            'supplier_id' => $request->input('supplier_id'),
            'description' => $request->input('description'),
            'total_amount' => (float) $request->input('total_amount'),
            'competence_date' => Carbon::parse($request->input('competence_date')),
            'notes' => $request->input('notes'),
            'source_type' => null,
            'source_id' => null,
            'source_snapshot' => null,
        ], array_map(function($inst, $index) {
            return [
                'installment_number' => $index + 1,
                'due_date' => $inst['due_date'],
                'amount' => (float) $inst['amount'],
            ];
        }, $installmentsData, array_keys($installmentsData)), auth()->user());

        return redirect()->route('payables.index')
            ->with('success', 'Contas a Pagar criado com sucesso.');
    }

    public function pay(Request $request, Payable $payable, PayableInstallment $installment): RedirectResponse
    {
        $this->authorize('update', $payable);

        $request->validate([
            'paid_amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'paid_at' => 'required|date',
            'discount_amount' => 'nullable|numeric|min:0',
            'interest_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $this->financialService->payPayableInstallment(
                $installment,
                (float) $request->input('paid_amount'),
                PaymentMethod::from($request->input('payment_method')),
                Carbon::parse($request->input('paid_at')),
                (float) $request->input('discount_amount', 0.00),
                (float) $request->input('interest_amount', 0.00),
                auth()->user()
            );

            return redirect()->route('payables.show', $payable)
                ->with('success', 'Parcela baixada com sucesso.');
        } catch (\Exception $e) {
            return redirect()->route('payables.show', $payable)
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function cancel(Payable $payable): RedirectResponse
    {
        $this->authorize('update', $payable);

        try {
            $this->financialService->cancelPayable($payable, auth()->user());
            return redirect()->route('payables.show', $payable)
                ->with('success', 'Título cancelado com sucesso.');
        } catch (\Exception $e) {
            return redirect()->route('payables.show', $payable)
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function print(Payable $payable): View
    {
        $this->authorize('view', $payable);
        $payable->load(['supplier', 'installments']);
        return view('payables.print', compact('payable'));
    }
}
