<?php

namespace App\Http\Controllers;

use App\Models\Receivable;
use App\Models\ReceivableInstallment;
use App\Models\Client;
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

class ReceivableController extends Controller
{
    public function __construct(
        private readonly FinancialService $financialService
    ) {
        $this->authorizeResource(Receivable::class, 'receivable');
    }

    public function index(Request $request): View
    {
        $query = Receivable::query()->with(['client', 'installments']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%")
                  ->orWhereHas('client', function($qc) use ($search) {
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

        $receivables = $query->latest()->paginate(15)->withQueryString();
        $statuses = PaymentStatus::cases();

        return view('receivables.index', compact('receivables', 'statuses'));
    }

    public function exportXlsx(Request $request)
    {
        $query = Receivable::query()->with(['client']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%")
                  ->orWhereHas('client', function($qc) use ($search) {
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

        $headers = ['Código', 'Cliente', 'Descrição', 'Valor Total', 'Status', 'Competência'];
        $formats = [
            'D' => 'currency',
            'F' => 'date',
        ];

        return app(ExportXlsxService::class)->export(
            'Recebíveis',
            $headers,
            $query,
            function ($receivable) {
                return [
                    $receivable->code,
                    $receivable->client->name ?? 'Cliente Avulso',
                    $receivable->description,
                    (float) $receivable->total_amount,
                    $receivable->status->label() ?? $receivable->status,
                    $receivable->competence_date ? $receivable->competence_date->format('Y-m-d') : '',
                ];
            },
            $formats,
            'Receivables.xlsx'
        );
    }

    public function show(Receivable $receivable): View
    {
        $receivable->load(['client', 'installments', 'events.user']);
        $paymentMethods = PaymentMethod::cases();
        return view('receivables.show', compact('receivable', 'paymentMethods'));
    }

    public function create(): View
    {
        $clients = Client::active()->orderBy('name')->get();
        return view('receivables.create', compact('clients'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'client_id' => 'nullable|exists:clients,id',
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

        $this->financialService->createReceivable([
            'company_id' => $company->id,
            'client_id' => $request->input('client_id'),
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

        return redirect()->route('receivables.index')
            ->with('success', 'Contas a Receber criado com sucesso.');
    }

    public function pay(Request $request, Receivable $receivable, ReceivableInstallment $installment): RedirectResponse
    {
        $this->authorize('update', $receivable);

        $request->validate([
            'paid_amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'paid_at' => 'required|date',
            'discount_amount' => 'nullable|numeric|min:0',
            'interest_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $this->financialService->payReceivableInstallment(
                $installment,
                (float) $request->input('paid_amount'),
                PaymentMethod::from($request->input('payment_method')),
                Carbon::parse($request->input('paid_at')),
                (float) $request->input('discount_amount', 0.00),
                (float) $request->input('interest_amount', 0.00),
                auth()->user()
            );

            if ($request->filled('redirect_to')) {
                return redirect($request->input('redirect_to'))
                    ->with('success', 'Parcela baixada com sucesso.');
            }

            return redirect()->route('receivables.show', $receivable)
                ->with('success', 'Parcela baixada com sucesso.');
        } catch (\Exception $e) {
            if ($request->filled('redirect_to')) {
                return redirect($request->input('redirect_to'))
                    ->withErrors(['error' => $e->getMessage()]);
            }
            return redirect()->route('receivables.show', $receivable)
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function cancel(Receivable $receivable): RedirectResponse
    {
        $this->authorize('update', $receivable);

        try {
            $this->financialService->cancelReceivable($receivable, auth()->user());
            return redirect()->route('receivables.show', $receivable)
                ->with('success', 'Título cancelado com sucesso.');
        } catch (\Exception $e) {
            return redirect()->route('receivables.show', $receivable)
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function print(Receivable $receivable): View
    {
        $this->authorize('view', $receivable);
        $receivable->load(['client', 'installments']);
        return view('receivables.print', compact('receivable'));
    }
}
