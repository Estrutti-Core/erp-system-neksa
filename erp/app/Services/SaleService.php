<?php

namespace App\Services;

use App\Models\Sale;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Enums\StockMovementSource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    /**
     * Fatura (conclui) uma venda e realiza a baixa do estoque físico.
     */
    public function complete(Sale $sale, User $user): Sale
    {
        if ($sale->status === SaleStatus::Completed) {
            throw ValidationException::withMessages([
                'status' => 'Esta venda já está faturada.',
            ]);
        }

        if ($sale->status === SaleStatus::Cancelled) {
            throw ValidationException::withMessages([
                'status' => 'Não é possível faturar uma venda cancelada.',
            ]);
        }

        return DB::transaction(function () use ($sale, $user) {
            $sale->update(['status' => SaleStatus::Completed]);

            $stockService = app(StockMovementService::class);
            foreach ($sale->items as $item) {
                if ($item->product_id && $item->product) {
                    $stockService->move(
                        $item->product,
                        -((float) $item->quantity),
                        StockMovementType::Output,
                        StockMovementSource::Sale,
                        $sale->id,
                        $user,
                        "Baixa automática por faturamento de Venda {$sale->code}"
                    );
                }
            }

            // Geração automática do Contas a Receber
            $snapshot = [
                'document_number' => $sale->code,
                'client_name' => $sale->client->name ?? 'Cliente Avulso',
                'total_amount' => (float) $sale->total_amount,
            ];

            $company = \App\Models\Company::first();
            if (!$company) {
                $company = \App\Models\Company::create([
                    'id' => 1,
                    'name' => 'Neksa ERP',
                ]);
            }
            $companyId = $company->id;

            $installments = [];
            $payments = $sale->payments;

            if ($payments && $payments->isNotEmpty()) {
                $instSeq = 1;
                foreach ($payments as $payment) {
                    $methodAmount = (float) $payment->amount;
                    $methodInstallments = (int) $payment->installments_count;
                    $firstDueDate = \Illuminate\Support\Carbon::parse($payment->first_due_date);
                    
                    $baseAmount = round($methodAmount / $methodInstallments, 2);
                    $remainder = round($methodAmount - ($baseAmount * $methodInstallments), 2);

                    for ($i = 1; $i <= $methodInstallments; $i++) {
                        $dueDate = $firstDueDate->copy()->addDays(($i - 1) * 30);
                        $installmentAmount = $baseAmount;
                        if ($i === $methodInstallments) {
                            $installmentAmount += $remainder;
                        }

                        $installments[] = [
                            'installment_number' => $instSeq++,
                            'due_date'           => $dueDate->toDateString(),
                            'amount'             => $installmentAmount,
                            'financial_account_id' => $payment->financial_account_id,
                        ];
                    }
                }
            } else {
                $account = \App\Models\FinancialAccount::where('is_active', true)->first();
                $installments[] = [
                    'installment_number' => 1,
                    'due_date'           => now()->toDateString(),
                    'amount'             => (float) $sale->total_amount,
                    'financial_account_id' => $account?->id ?? null,
                ];
            }

            app(\App\Services\FinancialService::class)->createReceivable([
                'company_id' => $companyId,
                'client_id' => $sale->client_id,
                'source_type' => get_class($sale),
                'source_id' => $sale->id,
                'source_snapshot' => $snapshot,
                'competence_date' => now(),
                'description' => "Contas a receber gerado pelo faturamento da venda {$sale->code}",
                'total_amount' => (float) $sale->total_amount,
            ], $installments, $user);

            return $sale->fresh();
        });
    }

    /**
     * Cancela uma venda e estorna o estoque se ela já havia sido faturada.
     */
    public function cancel(Sale $sale, User $user): Sale
    {
        if ($sale->status === SaleStatus::Cancelled) {
            throw ValidationException::withMessages([
                'status' => 'Esta venda já está cancelada.',
            ]);
        }

        $receivable = \App\Models\Receivable::where('source_type', get_class($sale))
            ->where('source_id', $sale->id)
            ->where('status', '!=', \App\Enums\PaymentStatus::Cancelled)
            ->first();

        if ($receivable) {
            $hasPaidAmount = $receivable->installments()->where('paid_amount', '>', 0.00)->exists();
            if ($hasPaidAmount) {
                throw ValidationException::withMessages([
                    'status' => 'Não é possível cancelar este documento porque existem movimentações financeiras já liquidadas. Realize o estorno financeiro antes de prosseguir.',
                ]);
            }
        }

        return DB::transaction(function () use ($sale, $user, $receivable) {
            $wasCompleted = $sale->status === SaleStatus::Completed;

            $sale->update(['status' => SaleStatus::Cancelled]);

            if ($wasCompleted) {
                $stockService = app(StockMovementService::class);
                foreach ($sale->items as $item) {
                    if ($item->product_id && $item->product) {
                        $stockService->move(
                            $item->product,
                            (float) $item->quantity,
                            StockMovementType::Input,
                            StockMovementSource::Sale,
                            $sale->id,
                            $user,
                            "Estorno automático por cancelamento de Venda {$sale->code}"
                        );
                    }
                }
            }

            if ($receivable) {
                app(\App\Services\FinancialService::class)->cancelReceivable($receivable, $user);
            }

            return $sale->fresh();
        });
    }
}
