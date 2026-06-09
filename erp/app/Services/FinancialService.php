<?php

namespace App\Services;

use App\Models\Receivable;
use App\Models\ReceivableInstallment;
use App\Models\Payable;
use App\Models\PayableInstallment;
use App\Models\FinancialEvent;
use App\Models\User;
use App\Enums\PaymentStatus;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialService
{
    /**
     * Gera o código sequencial para Contas a Receber.
     */
    private function generateReceivableCode(): string
    {
        $year = now()->year;
        $last = Receivable::whereYear('created_at', $year)
            ->lockForUpdate()
            ->latest('id')
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->code);
            $seq = ((int) end($parts)) + 1;
        }

        return 'REC-' . $year . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Gera o código sequencial para Contas a Pagar.
     */
    private function generatePayableCode(): string
    {
        $year = now()->year;
        $last = Payable::whereYear('created_at', $year)
            ->lockForUpdate()
            ->latest('id')
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->code);
            $seq = ((int) end($parts)) + 1;
        }

        return 'PAY-' . $year . '-' . str_pad($seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Cria um Contas a Receber.
     */
    public function createReceivable(array $data, array $installmentsData, ?User $user = null): Receivable
    {
        return DB::transaction(function () use ($data, $installmentsData, $user) {
            // Verifica idempotência antes de gerar
            if (!empty($data['source_type']) && !empty($data['source_id'])) {
                $exists = Receivable::where('source_type', $data['source_type'])
                    ->where('source_id', $data['source_id'])
                    ->where('status', '!=', PaymentStatus::Cancelled)
                    ->exists();
                if ($exists) {
                    // Já existe um título ativo para esta origem, retorna o primeiro encontrado
                    return Receivable::where('source_type', $data['source_type'])
                        ->where('source_id', $data['source_id'])
                        ->where('status', '!=', PaymentStatus::Cancelled)
                        ->first();
                }
            }

            $code = $this->generateReceivableCode();

            $receivable = Receivable::create([
                'company_id'      => $data['company_id'],
                'client_id'       => $data['client_id'] ?? null,
                'code'            => $code,
                'source_type'     => $data['source_type'] ?? null,
                'source_id'       => $data['source_id'] ?? null,
                'source_snapshot' => $data['source_snapshot'] ?? null,
                'competence_date' => $data['competence_date'] ?? Carbon::today(),
                'description'     => $data['description'],
                'total_amount'    => $data['total_amount'],
                'discount_amount' => 0.00,
                'interest_amount' => 0.00,
                'net_amount'      => $data['total_amount'],
                'status'          => PaymentStatus::Pending,
                'notes'           => $data['notes'] ?? null,
            ]);

            foreach ($installmentsData as $inst) {
                ReceivableInstallment::create([
                    'receivable_id'        => $receivable->id,
                    'installment_number'   => $inst['installment_number'],
                    'due_date'             => $inst['due_date'],
                    'amount'               => $inst['amount'],
                    'discount_amount'      => 0.00,
                    'interest_amount'      => 0.00,
                    'paid_amount'          => 0.00,
                    'paid_at'              => null,
                    'payment_method'       => null,
                    'status'               => InstallmentStatus::Pending,
                    'financial_account_id' => $inst['financial_account_id'] ?? null,
                ]);
            }

            // Log de auditoria financeira imutável
            FinancialEvent::create([
                'entity_type' => Receivable::class,
                'entity_id'   => $receivable->id,
                'event_type'  => 'created',
                'old_data'    => null,
                'new_data'    => $receivable->load('installments')->toArray(),
                'user_id'     => $user?->id ?? auth()->id(),
                'created_at'  => now(),
            ]);

            return $receivable;
        });
    }

    /**
     * Cria um Contas a Pagar.
     */
    public function createPayable(array $data, array $installmentsData, ?User $user = null): Payable
    {
        return DB::transaction(function () use ($data, $installmentsData, $user) {
            // Verifica idempotência
            if (!empty($data['source_type']) && !empty($data['source_id'])) {
                $exists = Payable::where('source_type', $data['source_type'])
                    ->where('source_id', $data['source_id'])
                    ->where('status', '!=', PaymentStatus::Cancelled)
                    ->exists();
                if ($exists) {
                    return Payable::where('source_type', $data['source_type'])
                        ->where('source_id', $data['source_id'])
                        ->where('status', '!=', PaymentStatus::Cancelled)
                        ->first();
                }
            }

            $code = $this->generatePayableCode();

            $payable = Payable::create([
                'company_id'      => $data['company_id'],
                'supplier_id'     => $data['supplier_id'] ?? null,
                'code'            => $code,
                'source_type'     => $data['source_type'] ?? null,
                'source_id'       => $data['source_id'] ?? null,
                'source_snapshot' => $data['source_snapshot'] ?? null,
                'competence_date' => $data['competence_date'] ?? Carbon::today(),
                'description'     => $data['description'],
                'total_amount'    => $data['total_amount'],
                'discount_amount' => 0.00,
                'interest_amount' => 0.00,
                'net_amount'      => $data['total_amount'],
                'status'          => PaymentStatus::Pending,
                'notes'           => $data['notes'] ?? null,
            ]);

            foreach ($installmentsData as $inst) {
                PayableInstallment::create([
                    'payable_id'           => $payable->id,
                    'installment_number'   => $inst['installment_number'],
                    'due_date'             => $inst['due_date'],
                    'amount'               => $inst['amount'],
                    'discount_amount'      => 0.00,
                    'interest_amount'      => 0.00,
                    'paid_amount'          => 0.00,
                    'paid_at'              => null,
                    'payment_method'       => null,
                    'status'               => InstallmentStatus::Pending,
                    'financial_account_id' => $inst['financial_account_id'] ?? null,
                ]);
            }

            FinancialEvent::create([
                'entity_type' => Payable::class,
                'entity_id'   => $payable->id,
                'event_type'  => 'created',
                'old_data'    => null,
                'new_data'    => $payable->load('installments')->toArray(),
                'user_id'     => $user?->id ?? auth()->id(),
                'created_at'  => now(),
            ]);

            return $payable;
        });
    }

    /**
     * Realiza a baixa de uma parcela de Contas a Receber.
     */
    public function payReceivableInstallment(
        ReceivableInstallment $installment,
        float $paidAmount,
        PaymentMethod $method,
        Carbon $paidAt,
        float $discount = 0.00,
        float $interest = 0.00,
        ?User $user = null,
        ?int $financialAccountId = null,
        ?string $notes = null
    ): ReceivableInstallment {
        return DB::transaction(function () use ($installment, $paidAmount, $method, $paidAt, $discount, $interest, $user, $financialAccountId, $notes) {
            // Locks
            $lockedInstallment = ReceivableInstallment::where('id', $installment->id)->lockForUpdate()->firstOrFail();
            $receivable = Receivable::where('id', $lockedInstallment->receivable_id)->lockForUpdate()->firstOrFail();

            if ($lockedInstallment->status === InstallmentStatus::Paid) {
                throw ValidationException::withMessages([
                    'installment' => 'Esta parcela ja se encontra totalmente quitada.',
                ]);
            }

            if ($receivable->status === PaymentStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'receivable' => 'Nao e permitido fazer baixas em um titulo cancelado.',
                ]);
            }

            $oldData = $lockedInstallment->toArray();

            // Resolução da conta financeira
            if (!$financialAccountId) {
                $account = \App\Models\FinancialAccount::where('is_active', true)->first();
                if (!$account) {
                    $type = \App\Models\FinancialAccountType::firstOrCreate(
                        ['slug' => 'cash'],
                        ['name' => 'Dinheiro / Caixa']
                    );
                    $account = \App\Models\FinancialAccount::create([
                        'name' => 'Caixa Geral',
                        'type_id' => $type->id,
                        'balance' => 0.00,
                        'is_active' => true,
                    ]);
                }
                $financialAccountId = $account->id;
            } else {
                $account = \App\Models\FinancialAccount::findOrFail($financialAccountId);
            }

            // Atualiza saldo da conta financeira (Recebível = Entrada = Incrementa Saldo)
            $account->balance = (float) $account->balance + $paidAmount;
            $account->save();

            // Salva dados da baixa na parcela
            $newPaidAmount = (float) $lockedInstallment->paid_amount + $paidAmount;
            $totalRequired = ((float) $lockedInstallment->amount + $interest) - $discount;

            $lockedInstallment->paid_amount = $newPaidAmount;
            $lockedInstallment->discount_amount = (float) $lockedInstallment->discount_amount + $discount;
            $lockedInstallment->interest_amount = (float) $lockedInstallment->interest_amount + $interest;
            $lockedInstallment->net_amount = ((float) $lockedInstallment->amount + $interest) - $discount;
            $lockedInstallment->paid_at = $paidAt;
            $lockedInstallment->payment_method = $method;
            $lockedInstallment->financial_account_id = $financialAccountId;
            $lockedInstallment->user_id = $user?->id ?? auth()->id();
            $lockedInstallment->notes = $notes;

            if ($newPaidAmount >= $totalRequired - 0.0001) {
                $lockedInstallment->status = InstallmentStatus::Paid;
                $eventType = 'full_payment';
            } else {
                $lockedInstallment->status = InstallmentStatus::Pending;
                $eventType = 'partial_payment';
            }

            $lockedInstallment->save();

            // Atualiza totais e status do Contas a Receber pai
            $totalDiscount = $receivable->installments()->sum('discount_amount');
            $totalInterest = $receivable->installments()->sum('interest_amount');
            
            $receivable->discount_amount = $totalDiscount;
            $receivable->interest_amount = $totalInterest;
            $receivable->net_amount = ($receivable->total_amount + $totalInterest) - $totalDiscount;

            $activeInstallments = $receivable->installments;
            $nonCancelled = $activeInstallments->filter(fn($i) => $i->status !== InstallmentStatus::Cancelled);

            if ($nonCancelled->isEmpty()) {
                $receivable->status = PaymentStatus::Cancelled;
            } elseif ($nonCancelled->every(fn($i) => $i->status === InstallmentStatus::Paid)) {
                $receivable->status = PaymentStatus::Paid;
            } elseif ($nonCancelled->some(fn($i) => $i->status === InstallmentStatus::Paid || $i->paid_amount > 0)) {
                $receivable->status = PaymentStatus::PartiallyPaid;
            } else {
                $receivable->status = PaymentStatus::Pending;
            }
            $receivable->save();

            // Grava log de auditoria financeira
            FinancialEvent::create([
                'entity_type' => ReceivableInstallment::class,
                'entity_id'   => $lockedInstallment->id,
                'event_type'  => $eventType,
                'old_data'    => $oldData,
                'new_data'    => $lockedInstallment->toArray(),
                'user_id'     => $user?->id ?? auth()->id(),
                'created_at'  => now(),
            ]);

            return $lockedInstallment;
        });
    }

    /**
     * Realiza a baixa de uma parcela de Contas a Pagar.
     */
    public function payPayableInstallment(
        PayableInstallment $installment,
        float $paidAmount,
        PaymentMethod $method,
        Carbon $paidAt,
        float $discount = 0.00,
        float $interest = 0.00,
        ?User $user = null,
        ?int $financialAccountId = null,
        ?string $notes = null
    ): PayableInstallment {
        return DB::transaction(function () use ($installment, $paidAmount, $method, $paidAt, $discount, $interest, $user, $financialAccountId, $notes) {
            $lockedInstallment = PayableInstallment::where('id', $installment->id)->lockForUpdate()->firstOrFail();
            $payable = Payable::where('id', $lockedInstallment->payable_id)->lockForUpdate()->firstOrFail();

            if ($lockedInstallment->status === InstallmentStatus::Paid) {
                throw ValidationException::withMessages([
                    'installment' => 'Esta parcela ja se encontra totalmente quitada.',
                ]);
            }

            if ($payable->status === PaymentStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'payable' => 'Nao e permitido fazer baixas em um titulo cancelado.',
                ]);
            }

            $oldData = $lockedInstallment->toArray();

            // Resolução da conta financeira
            if (!$financialAccountId) {
                $account = \App\Models\FinancialAccount::where('is_active', true)->first();
                if (!$account) {
                    $type = \App\Models\FinancialAccountType::firstOrCreate(
                        ['slug' => 'cash'],
                        ['name' => 'Dinheiro / Caixa']
                    );
                    $account = \App\Models\FinancialAccount::create([
                        'name' => 'Caixa Geral',
                        'type_id' => $type->id,
                        'balance' => 0.00,
                        'is_active' => true,
                    ]);
                }
                $financialAccountId = $account->id;
            } else {
                $account = \App\Models\FinancialAccount::findOrFail($financialAccountId);
            }

            // Atualiza saldo da conta financeira (Pagável = Saída = Decrementa Saldo)
            $account->balance = (float) $account->balance - $paidAmount;
            $account->save();

            $newPaidAmount = (float) $lockedInstallment->paid_amount + $paidAmount;
            $totalRequired = ((float) $lockedInstallment->amount + $interest) - $discount;

            $lockedInstallment->paid_amount = $newPaidAmount;
            $lockedInstallment->discount_amount = (float) $lockedInstallment->discount_amount + $discount;
            $lockedInstallment->interest_amount = (float) $lockedInstallment->interest_amount + $interest;
            $lockedInstallment->net_amount = ((float) $lockedInstallment->amount + $interest) - $discount;
            $lockedInstallment->paid_at = $paidAt;
            $lockedInstallment->payment_method = $method;
            $lockedInstallment->financial_account_id = $financialAccountId;
            $lockedInstallment->user_id = $user?->id ?? auth()->id();
            $lockedInstallment->notes = $notes;

            if ($newPaidAmount >= $totalRequired - 0.0001) {
                $lockedInstallment->status = InstallmentStatus::Paid;
                $eventType = 'full_payment';
            } else {
                $lockedInstallment->status = InstallmentStatus::Pending;
                $eventType = 'partial_payment';
            }

            $lockedInstallment->save();

            $totalDiscount = $payable->installments()->sum('discount_amount');
            $totalInterest = $payable->installments()->sum('interest_amount');
            
            $payable->discount_amount = $totalDiscount;
            $payable->interest_amount = $totalInterest;
            $payable->net_amount = ($payable->total_amount + $totalInterest) - $totalDiscount;

            $activeInstallments = $payable->installments;
            $nonCancelled = $activeInstallments->filter(fn($i) => $i->status !== InstallmentStatus::Cancelled);

            if ($nonCancelled->isEmpty()) {
                $payable->status = PaymentStatus::Cancelled;
            } elseif ($nonCancelled->every(fn($i) => $i->status === InstallmentStatus::Paid)) {
                $payable->status = PaymentStatus::Paid;
            } elseif ($nonCancelled->some(fn($i) => $i->status === InstallmentStatus::Paid || $i->paid_amount > 0)) {
                $payable->status = PaymentStatus::PartiallyPaid;
            } else {
                $payable->status = PaymentStatus::Pending;
            }
            $payable->save();

            FinancialEvent::create([
                'entity_type' => PayableInstallment::class,
                'entity_id'   => $lockedInstallment->id,
                'event_type'  => $eventType,
                'old_data'    => $oldData,
                'new_data'    => $lockedInstallment->toArray(),
                'user_id'     => $user?->id ?? auth()->id(),
                'created_at'  => now(),
            ]);

            return $lockedInstallment;
        });
    }

    /**
     * Cancela um Contas a Receber.
     */
    public function cancelReceivable(Receivable $receivable, ?User $user = null): Receivable
    {
        return DB::transaction(function () use ($receivable, $user) {
            $lockedReceivable = Receivable::where('id', $receivable->id)->lockForUpdate()->firstOrFail();

            if ($lockedReceivable->status === PaymentStatus::Cancelled) {
                return $lockedReceivable;
            }

            // Impede cancelamento se houver qualquer valor já liquidado nas parcelas
            $hasPaidAmount = $lockedReceivable->installments()->where('paid_amount', '>', 0.00)->exists();
            if ($hasPaidAmount) {
                throw ValidationException::withMessages([
                    'status' => 'Não é possível cancelar este documento porque existem movimentações financeiras já liquidadas. Realize o estorno financeiro antes de prosseguir.',
                ]);
            }

            $oldData = $lockedReceivable->load('installments')->toArray();

            // Cancela todas as parcelas
            $lockedReceivable->installments()->update([
                'status' => InstallmentStatus::Cancelled,
            ]);

            $lockedReceivable->status = PaymentStatus::Cancelled;
            $lockedReceivable->save();

            FinancialEvent::create([
                'entity_type' => Receivable::class,
                'entity_id'   => $lockedReceivable->id,
                'event_type'  => 'cancelled',
                'old_data'    => $oldData,
                'new_data'    => $lockedReceivable->load('installments')->toArray(),
                'user_id'     => $user?->id ?? auth()->id(),
                'created_at'  => now(),
            ]);

            return $lockedReceivable;
        });
    }

    /**
     * Cancela um Contas a Pagar.
     */
    public function cancelPayable(Payable $payable, ?User $user = null): Payable
    {
        return DB::transaction(function () use ($payable, $user) {
            $lockedPayable = Payable::where('id', $payable->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayable->status === PaymentStatus::Cancelled) {
                return $lockedPayable;
            }

            $hasPaidAmount = $lockedPayable->installments()->where('paid_amount', '>', 0.00)->exists();
            if ($hasPaidAmount) {
                throw ValidationException::withMessages([
                    'status' => 'Não é possível cancelar este documento porque existem movimentações financeiras já liquidadas. Realize o estorno financeiro antes de prosseguir.',
                ]);
            }

            $oldData = $lockedPayable->load('installments')->toArray();

            $lockedPayable->installments()->update([
                'status' => InstallmentStatus::Cancelled,
            ]);

            $lockedPayable->status = PaymentStatus::Cancelled;
            $lockedPayable->save();

            FinancialEvent::create([
                'entity_type' => Payable::class,
                'entity_id'   => $lockedPayable->id,
                'event_type'  => 'cancelled',
                'old_data'    => $oldData,
                'new_data'    => $lockedPayable->load('installments')->toArray(),
                'user_id'     => $user?->id ?? auth()->id(),
                'created_at'  => now(),
            ]);

            return $lockedPayable;
        });
    }

    /**
     * Consolida o Fluxo de Caixa da Empresa.
     */
    public function getCashFlow(Carbon $startDate, Carbon $endDate, string $regime): array
    {
        $startDateStr = $startDate->toDateString();
        $endDateStr = $endDate->toDateString();

        if ($regime === 'caixa') {
            // Regime de Caixa: baseado na data de pagamento (paid_at) das parcelas pagas
            $receivables = DB::table('receivable_installments')
                ->join('receivables', 'receivable_installments.receivable_id', '=', 'receivables.id')
                ->where('receivable_installments.status', InstallmentStatus::Paid->value)
                ->whereBetween(DB::raw('DATE(receivable_installments.paid_at)'), [$startDateStr, $endDateStr])
                ->select(
                    DB::raw('DATE(receivable_installments.paid_at) as date'),
                    DB::raw('SUM(receivable_installments.paid_amount) as amount')
                )
                ->groupBy('date')
                ->get()
                ->pluck('amount', 'date')
                ->toArray();

            $payables = DB::table('payable_installments')
                ->join('payables', 'payable_installments.payable_id', '=', 'payables.id')
                ->where('payable_installments.status', InstallmentStatus::Paid->value)
                ->whereBetween(DB::raw('DATE(payable_installments.paid_at)'), [$startDateStr, $endDateStr])
                ->select(
                    DB::raw('DATE(payable_installments.paid_at) as date'),
                    DB::raw('SUM(payable_installments.paid_amount) as amount')
                )
                ->groupBy('date')
                ->get()
                ->pluck('amount', 'date')
                ->toArray();
        } else {
            // Regime de Competência: baseado na competence_date dos títulos ativos (não cancelados)
            $receivables = DB::table('receivables')
                ->where('status', '!=', PaymentStatus::Cancelled->value)
                ->whereBetween('competence_date', [$startDateStr, $endDateStr])
                ->select(
                    'competence_date as date',
                    DB::raw('SUM(total_amount) as amount')
                )
                ->groupBy('date')
                ->get()
                ->pluck('amount', 'date')
                ->toArray();

            $payables = DB::table('payables')
                ->where('status', '!=', PaymentStatus::Cancelled->value)
                ->whereBetween('competence_date', [$startDateStr, $endDateStr])
                ->select(
                    'competence_date as date',
                    DB::raw('SUM(total_amount) as amount')
                )
                ->groupBy('date')
                ->get()
                ->pluck('amount', 'date')
                ->toArray();
        }

        // Consolida por data
        $flow = [];
        $current = $startDate->copy();
        $totalIn = 0.00;
        $totalOut = 0.00;

        while ($current->lte($endDate)) {
            $dateStr = $current->toDateString();
            $in = (float) ($receivables[$dateStr] ?? 0.00);
            $out = (float) ($payables[$dateStr] ?? 0.00);

            $totalIn += $in;
            $totalOut += $out;

            $flow[$dateStr] = [
                'inputs' => $in,
                'outputs' => $out,
                'balance' => $in - $out,
            ];

            $current->addDay();
        }

        return [
            'timeline' => $flow,
            'total_inputs' => $totalIn,
            'total_outputs' => $totalOut,
            'net_balance' => $totalIn - $totalOut,
        ];
    }
}
