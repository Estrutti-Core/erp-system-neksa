<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Enums\SaleStatus;
use App\Services\ExportXlsxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;

class SaleController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Sale::class, 'sale');
    }

    public function index(Request $request): View
    {
        $sales = Sale::query()
            ->when($request->search, fn ($q, $s) => $q->search($s))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->with(['client'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $statuses = SaleStatus::cases();

        return view('sales.index', compact('sales', 'statuses'));
    }

    public function exportXlsx(Request $request)
    {
        $query = Sale::query()
            ->when($request->search, fn ($q, $s) => $q->search($s))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->with(['client']);

        $query->latest();

        $headers = ['Código', 'Cliente', 'Valor Total', 'Status', 'Data Venda'];
        $formats = [
            'C' => 'currency',
            'E' => 'date',
        ];

        return app(ExportXlsxService::class)->export(
            'Vendas',
            $headers,
            $query,
            function ($sale) {
                return [
                    $sale->code,
                    $sale->client->name ?? 'Consumidor',
                    (float) $sale->total_amount,
                    $sale->status->label() ?? $sale->status,
                    $sale->created_at ? $sale->created_at->format('Y-m-d') : '',
                ];
            },
            $formats,
            'Sales.xlsx'
        );
    }

    public function show(Sale $sale): View
    {
        $sale->load(['client', 'clientAddress', 'items.product', 'items.service', 'quote', 'payments', 'attachments.uploader', 'receivable.installments']);
        $accounts = \App\Models\FinancialAccount::where('is_active', true)->get();
        $paymentMethods = \App\Enums\PaymentMethod::cases();
        return view('sales.show', compact('sale', 'accounts', 'paymentMethods'));
    }

    /**
     * Gera o PDF da Venda.
     */
    public function pdf(Sale $sale): \Illuminate\Http\Response
    {
        $this->authorize('view', $sale);

        $sale->load(['client', 'clientAddress', 'items.product', 'items.service']);

        $pdf = Pdf::loadView('pdf.sale', compact('sale'))->setPaper('a4');

        $clientName = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($sale->client->name));
        $number = preg_replace('/^VEN-/', '', $sale->code);
        $filename = "{$clientName}-VENDA-{$number}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Exibe o formulário de pagamento para faturamento da venda.
     */
    public function paymentForm(Sale $sale): View|RedirectResponse
    {
        $this->authorize('update', $sale);

        if ($sale->status === SaleStatus::Completed) {
            return redirect()->route('sales.show', $sale)
                ->with('error', 'Esta venda já está faturada.');
        }

        if ($sale->status === SaleStatus::Cancelled) {
            return redirect()->route('sales.show', $sale)
                ->with('error', 'Não é possível faturar uma venda cancelada.');
        }

        $accounts = \App\Models\FinancialAccount::where('is_active', true)->get();
        $methods = \App\Enums\PaymentMethod::cases();
        $paymentConditions = \App\Models\PaymentCondition::where('is_active', true)->get();

        return view('sales.payment', compact('sale', 'accounts', 'methods', 'paymentConditions'));
    }

    /**
     * Fatura (conclui) a venda e realiza a baixa do estoque.
     */
    public function complete(Request $request, Sale $sale, \App\Services\SaleService $saleService): RedirectResponse
    {
        $this->authorize('update', $sale);

        if ($request->has('installments')) {
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

            // Valida se a soma das parcelas bate com o total da venda
            $totalPayments = array_sum(array_column($validated['installments'], 'amount'));
            if (abs($totalPayments - (float) $sale->total_amount) > 0.01) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', sprintf('A soma das parcelas (R$ %s) deve ser exatamente igual ao valor total da venda (R$ %s).', number_format($totalPayments, 2, ',', '.'), number_format((float) $sale->total_amount, 2, ',', '.')));
            }

            try {
                DB::transaction(function () use ($sale, $validated, $saleService) {
                    // Remove pagamentos existentes se houver
                    $sale->payments()->delete();

                    // Cria os novos registros de pagamento a partir das parcelas customizadas
                    foreach ($validated['installments'] as $inst) {
                        $sale->payments()->create([
                            'payment_method'       => $inst['payment_method'],
                            'amount'               => $inst['amount'],
                            'installments_count'   => 1,
                            'first_due_date'       => $inst['due_date'],
                            'financial_account_id' => $inst['financial_account_id'],
                        ]);
                    }

                    $saleService->complete($sale, auth()->user());
                });

                return redirect()->route('sales.show', $sale)
                    ->with('success', 'Venda faturada e estoque baixado com sucesso!');
            } catch (Exception $e) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Erro ao faturar venda: ' . $e->getMessage());
            }
        }

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

        // Valida se a soma dos valores informados bate com o total da venda
        $totalPayments = array_sum(array_column($validated['payments'], 'amount'));
        if (abs($totalPayments - (float) $sale->total_amount) > 0.01) {
            return redirect()->back()
                ->withInput()
                ->with('error', sprintf('A soma dos pagamentos (R$ %s) deve ser exatamente igual ao valor total da venda (R$ %s).', number_format($totalPayments, 2, ',', '.'), number_format((float) $sale->total_amount, 2, ',', '.')));
        }

        try {
            DB::transaction(function () use ($sale, $validated, $saleService) {
                // Remove pagamentos existentes se houver
                $sale->payments()->delete();

                // Cria os novos registros de pagamento
                foreach ($validated['payments'] as $paymentData) {
                    $sale->payments()->create([
                        'payment_method'       => $paymentData['payment_method'],
                        'amount'               => $paymentData['amount'],
                        'installments_count'   => $paymentData['installments_count'],
                        'first_due_date'       => $paymentData['first_due_date'],
                        'financial_account_id' => $paymentData['financial_account_id'],
                    ]);
                }

                $saleService->complete($sale, auth()->user());
            });

            return redirect()->route('sales.show', $sale)
                ->with('success', 'Venda faturada e estoque baixado com sucesso!');
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Erro ao faturar venda: ' . $e->getMessage());
        }
    }

    /**
     * Cancela a venda e devolve os itens ao estoque se controlados.
     */
    public function cancel(Sale $sale, \App\Services\SaleService $saleService): RedirectResponse
    {
        try {
            $saleService->cancel($sale, auth()->user());
            return redirect()->route('sales.show', $sale)
                ->with('success', 'Venda cancelada e estoque estornado com sucesso!');
        } catch (Exception $e) {
            return redirect()->back()
                ->with('error', 'Erro ao cancelar venda: ' . $e->getMessage());
        }
    }

    /**
     * Upload de múltiplos anexos da venda.
     */
    public function uploadAttachments(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('update', $sale);

        $request->validate([
            'attachments'   => ['required', 'array', 'min:1'],
            'attachments.*' => ['required', 'file', 'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,docx,xlsx,doc,xls'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $caption = $request->input('caption');

        foreach ($request->file('attachments') as $file) {
            $path = $file->store("sale_attachments/{$sale->id}", 'public');

            $sale->attachments()->create([
                'uploaded_by'   => auth()->id(),
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'disk'          => 'public',
                'type'          => 'general',
                'caption'       => $caption,
                'size'          => $file->getSize(),
                'mime_type'     => $file->getMimeType(),
            ]);
        }

        return back()->with('success', 'Anexo(s) enviado(s) com sucesso!');
    }

    /**
     * Remove um anexo da venda.
     */
    public function destroyAttachment(Sale $sale, \App\Models\SaleAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $sale);

        abort_if($attachment->sale_id !== $sale->id, 404);

        \Illuminate\Support\Facades\Storage::disk($attachment->disk ?? 'public')->delete($attachment->path);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($attachment)
            ->withProperties(['path' => $attachment->path, 'original_name' => $attachment->original_name])
            ->log('sale_attachment_deleted');

        $attachment->delete();

        return back()->with('success', 'Anexo removido.');
    }
}
