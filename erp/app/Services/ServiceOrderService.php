<?php

namespace App\Services;

use App\Models\ServiceOrderStatus;
use App\Models\ServiceOrderStatusHistory;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceOrderService
{
    /**
     * Cria uma nova Ordem de Serviço com código sequencial e histórico.
     */
    public function create(array $data, User $creator): ServiceOrder
    {
        return DB::transaction(function () use ($data, $creator) {
            $data['code']       = $this->generateCode();
            $data['created_by'] = $creator->id;
            $openStatus = ServiceOrderStatus::where('slug', 'open')->firstOrFail();
            $data['status_id'] = $openStatus->id;

            $serviceOrder = ServiceOrder::create($data);

            // 1. Create entry in dedicated status history
            ServiceOrderStatusHistory::create([
                'service_order_id' => $serviceOrder->id,
                'from_status_id'   => null,
                'to_status_id'     => $openStatus->id,
                'changed_by'       => $creator->id,
                'entered_at'       => now(),
                'notes'            => 'Ordem de Serviço criada.',
            ]);

            // 2. Keep the text-based ServiceOrderHistory for general timeline
            ServiceOrderHistory::create([
                'service_order_id' => $serviceOrder->id,
                'user_id'          => $creator->id,
                'event'            => 'created',
                'to_status'        => 'open',
                'description'      => 'Ordem de Serviço criada.',
            ]);

            // 3. Dispatch domain event
            event(new \App\Events\ServiceOrderEnteredStatus($serviceOrder, $openStatus, now()));

            // 4. Sync required checklists
            (new \App\Services\ServiceOrderChecklistService())->syncRequiredChecklists($serviceOrder);

            return $serviceOrder;
        });
    }

    /**
     * Atualiza uma OS existente.
     */
    public function update(ServiceOrder $serviceOrder, array $data, User $user): ServiceOrder
    {
        return DB::transaction(function () use ($serviceOrder, $data, $user) {
            $serviceOrder->update($data);

            ServiceOrderHistory::create([
                'service_order_id' => $serviceOrder->id,
                'user_id'          => $user->id,
                'event'            => 'updated',
                'description'      => 'Dados da OS atualizados.',
            ]);

            // Sync required checklists
            (new \App\Services\ServiceOrderChecklistService())->syncRequiredChecklists($serviceOrder);

            return $serviceOrder->fresh();
        });
    }

    /**
     * Realiza a transição de status com validação de estados permitidos.
     *
     * @throws ValidationException
     */
    public function changeStatus(ServiceOrder $serviceOrder, ServiceOrderStatus $newStatus, User $user, ?string $note = null): ServiceOrder
    {
        $oldStatus = $serviceOrder->status; // ServiceOrderStatus Model

        if (!$oldStatus->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Não é possível alterar o status de '{$oldStatus->name}' para '{$newStatus->name}'.",
            ]);
        }

        if ($newStatus->is_completed_state) {
            if (!$serviceOrder->allChecklistsFilled()) {
                throw ValidationException::withMessages([
                    'status' => 'Não é possível concluir a OS pois existem checklists ativos pendentes de preenchimento.',
                ]);
            }
            if (!$serviceOrder->allRequiredAnswersFilled()) {
                throw ValidationException::withMessages([
                    'status' => 'Não é possível concluir a OS pois existem perguntas obrigatórias sem resposta.',
                ]);
            }
            if (!$serviceOrder->hasSignature()) {
                throw ValidationException::withMessages([
                    'status' => 'Não é possível concluir a OS pois a assinatura do cliente ainda não foi coletada.',
                ]);
            }
            if (!$serviceOrder->hasCheckin()) {
                throw ValidationException::withMessages([
                    'status' => 'Não é possível concluir a OS pois nenhum check-in de chegada foi registrado.',
                ]);
            }
        }

        if ($newStatus->is_cancelled_state) {
            $receivable = \App\Models\Receivable::where('source_type', get_class($serviceOrder))
                ->where('source_id', $serviceOrder->id)
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
        }

        return DB::transaction(function () use ($serviceOrder, $oldStatus, $newStatus, $user, $note) {
            $serviceOrder->status_id = $newStatus->id;

            if ($newStatus->slug === 'in_service' && !$serviceOrder->started_at) {
                $serviceOrder->started_at = \Illuminate\Support\Carbon::now();
            }

            if ($newStatus->is_completed_state) {
                $serviceOrder->completed_at = \Illuminate\Support\Carbon::now();
            }

            $serviceOrder->save();

            // ─── Integração Financeira ────────────────────────────────────────
            if ($newStatus->is_completed_state) {
                $snapshot = [
                    'document_number' => $serviceOrder->code,
                    'client_name' => $serviceOrder->client->name ?? 'Cliente Avulso',
                    'total_amount' => (float) $serviceOrder->total_amount,
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
                $payments = $serviceOrder->payments;

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
                        'amount'             => (float) $serviceOrder->total_amount,
                        'financial_account_id' => $account?->id ?? null,
                    ];
                }

                app(\App\Services\FinancialService::class)->createReceivable([
                    'company_id' => $companyId,
                    'client_id' => $serviceOrder->client_id,
                    'source_type' => get_class($serviceOrder),
                    'source_id' => $serviceOrder->id,
                    'source_snapshot' => $snapshot,
                    'competence_date' => now(),
                    'description' => "Contas a receber gerado pela conclusao da OS {$serviceOrder->code}",
                    'total_amount' => (float) $serviceOrder->total_amount,
                ], $installments, $user);
            }

            if ($newStatus->is_cancelled_state) {
                $receivable = \App\Models\Receivable::where('source_type', get_class($serviceOrder))
                    ->where('source_id', $serviceOrder->id)
                    ->where('status', '!=', \App\Enums\PaymentStatus::Cancelled)
                    ->first();
                if ($receivable) {
                    app(\App\Services\FinancialService::class)->cancelReceivable($receivable, $user);
                }
            }

            // ─── Controle de Estoque (Módulo E) ───────────────────────────────
            if ($newStatus->is_completed_state) {
                $stockService = app(\App\Services\StockMovementService::class);
                foreach ($serviceOrder->items as $item) {
                    if ($item->product_id && $item->product) {
                        $stockService->move(
                            $item->product,
                            -((float) $item->quantity),
                            \App\Enums\StockMovementType::Output,
                            \App\Enums\StockMovementSource::ServiceOrder,
                            $serviceOrder->id,
                            $user,
                            "Baixa automática por conclusão da OS {$serviceOrder->code}"
                        );
                    }
                }
            }

            if ($newStatus->is_cancelled_state) {
                $wasCompletedBefore = ServiceOrderStatusHistory::where('service_order_id', $serviceOrder->id)
                    ->whereIn('to_status_id', ServiceOrderStatus::where('is_completed_state', true)->pluck('id'))
                    ->exists();

                if ($wasCompletedBefore) {
                    $stockService = app(\App\Services\StockMovementService::class);
                    foreach ($serviceOrder->items as $item) {
                        if ($item->product_id && $item->product) {
                            $stockService->move(
                                $item->product,
                                (float) $item->quantity,
                                \App\Enums\StockMovementType::Input,
                                \App\Enums\StockMovementSource::ServiceOrder,
                                $serviceOrder->id,
                                $user,
                                "Estorno automático por cancelamento da OS {$serviceOrder->code}"
                            );
                        }
                    }
                }
            }
            // ──────────────────────────────────────────────────────────────────

            // 1. Close current status history entry
            $currentHistory = ServiceOrderStatusHistory::where('service_order_id', $serviceOrder->id)
                ->whereNull('left_at')
                ->latest('entered_at')
                ->first();

            $now = now();
            if ($currentHistory) {
                $currentHistory->left_at = $now;
                $currentHistory->duration_minutes = (int) max(0, $currentHistory->entered_at->diffInMinutes($now));
                $currentHistory->save();
            }

            // 2. Open new status history entry
            ServiceOrderStatusHistory::create([
                'service_order_id' => $serviceOrder->id,
                'from_status_id'   => $oldStatus->id,
                'to_status_id'     => $newStatus->id,
                'changed_by'       => $user->id,
                'entered_at'       => $now,
                'notes'            => $note,
            ]);

            // 3. Keep old service_order_histories as backup/timeline
            ServiceOrderHistory::create([
                'service_order_id' => $serviceOrder->id,
                'user_id'          => $user->id,
                'event'            => 'status_changed',
                'from_status'      => $oldStatus->slug,
                'to_status'        => $newStatus->slug,
                'description'      => $note ?? "Status alterado de '{$oldStatus->name}' para '{$newStatus->name}'.",
            ]);

            // 4. Dispatch Domain Events
            event(new \App\Events\ServiceOrderStatusChanged($serviceOrder, $oldStatus, $newStatus, $user, $note));
            event(new \App\Events\ServiceOrderEnteredStatus($serviceOrder, $newStatus, $now));

            return $serviceOrder->fresh();
        });
    }

    /**
     * Atribui um técnico à OS.
     */
    public function assignTechnician(ServiceOrder $serviceOrder, User $technician, User $assignedBy): ServiceOrder
    {
        return DB::transaction(function () use ($serviceOrder, $technician, $assignedBy) {
            $serviceOrder->update(['technician_id' => $technician->id]);

            ServiceOrderHistory::create([
                'service_order_id' => $serviceOrder->id,
                'user_id'          => $assignedBy->id,
                'event'            => 'technician_assigned',
                'description'      => "Técnico '{$technician->name}' atribuído à OS.",
                'metadata'         => ['technician_id' => $technician->id, 'technician_name' => $technician->name],
            ]);

            return $serviceOrder->fresh();
        });
    }

    /**
     * Registra check-in do técnico com coordenadas GPS.
     */
    public function checkIn(ServiceOrder $serviceOrder, float $lat, float $lng, User $user): ServiceOrder
    {
        return DB::transaction(function () use ($serviceOrder, $lat, $lng, $user) {
            $serviceOrder->update([
                'checkin_latitude'  => $lat,
                'checkin_longitude' => $lng,
                'checkin_at'        => now(),
            ]);

            ServiceOrderHistory::create([
                'service_order_id' => $serviceOrder->id,
                'user_id'          => $user->id,
                'event'            => 'checkin',
                'description'      => 'Check-in realizado pelo técnico.',
                'metadata'         => ['latitude' => $lat, 'longitude' => $lng],
            ]);

            return $serviceOrder->fresh();
        });
    }

    /**
     * Duplica uma OS existente, reiniciando o fluxo de status, zerando histórico e execuções/assinaturas.
     */
    public function duplicate(ServiceOrder $source, User $creator): ServiceOrder
    {
        return DB::transaction(function () use ($source, $creator) {
            $newCode = $this->generateCode();
            $openStatus = ServiceOrderStatus::where('slug', 'open')->firstOrFail();
            
            $newOrder = ServiceOrder::create([
                'code'              => $newCode,
                'client_id'         => $source->client_id,
                'client_address_id' => $source->client_address_id,
                'equipment_id'      => $source->equipment_id,
                'technician_id'     => $source->technician_id,
                'created_by'        => $creator->id,
                'status_id'         => $openStatus->id,
                'priority'          => $source->priority->value,
                'description'       => $source->description,
                'internal_notes'    => $source->internal_notes,
                'total_amount'      => $source->total_amount,
                'service_amount'    => $source->service_amount,
                'parts_amount'      => $source->parts_amount,
                'scheduled_at'      => $source->scheduled_at,
            ]);

            // Duplica os itens da OS
            foreach ($source->items as $item) {
                $newOrder->items()->create([
                    'product_id'   => $item->product_id,
                    'service_id'   => $item->service_id,
                    'type'         => $item->type,
                    'description'  => $item->description,
                    'quantity'     => $item->quantity,
                    'unit'         => $item->unit,
                    'unit_price'   => $item->unit_price,
                    'product_code' => $item->product_code,
                ]);
            }

            // Criar histórico de status dedicado
            ServiceOrderStatusHistory::create([
                'service_order_id' => $newOrder->id,
                'from_status_id'   => null,
                'to_status_id'     => $openStatus->id,
                'changed_by'       => $creator->id,
                'entered_at'       => now(),
                'notes'            => 'Ordem de serviço duplicada.',
            ]);

            // Histórico inicial de timeline
            ServiceOrderHistory::create([
                'service_order_id' => $newOrder->id,
                'user_id'          => $creator->id,
                'event'            => 'created',
                'to_status'        => 'open',
                'description'      => "Ordem de Serviço criada por duplicação da OS {$source->code}.",
            ]);

            // Disparar evento
            event(new \App\Events\ServiceOrderEnteredStatus($newOrder, $openStatus, now()));

            // Sync checklists for duplicated OS
            (new \App\Services\ServiceOrderChecklistService())->syncRequiredChecklists($newOrder);

            return $newOrder;
        });
    }

    /**
     * Gera um código sequencial de OS no formato OS-2024-00001.
     */
    private function generateCode(): string
    {
        $year  = now()->year;
        $count = ServiceOrder::whereYear('created_at', $year)->count() + 1;

        return sprintf('OS-%s-%05d', $year, $count);
    }
}
