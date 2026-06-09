<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\InventoryConference;
use App\Models\InventoryConferenceItem;
use App\Models\User;
use App\Models\Product;
use App\Enums\PurchaseOrderStatus;
use App\Enums\InventoryConferenceStatus;
use App\Enums\StockMovementType;
use App\Enums\StockMovementSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    /**
     * Envia o Pedido de Compra (muda status para Ordered).
     */
    public function order(PurchaseOrder $order, User $user): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Apenas pedidos em rascunho podem ser emitidos/enviados.',
            ]);
        }

        if ($order->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => 'O pedido de compra não possui itens cadastrados.',
            ]);
        }

        $order->status = PurchaseOrderStatus::Ordered;
        $order->save();

        return $order;
    }

    /**
     * Calcula o saldo pendente de entrega por produto de um Pedido de Compra.
     * Retorna array: [product_id => quantity_pending]
     */
    public function getPendingBalances(PurchaseOrder $order): array
    {
        $balances = [];

        foreach ($order->items as $item) {
            // Soma todas as quantidades já recebidas nas conferências concluídas
            $receivedSum = (float) InventoryConferenceItem::whereHas('inventoryConference', function ($q) use ($order) {
                    $q->where('purchase_order_id', $order->id)
                      ->whereIn('status', [InventoryConferenceStatus::Completed, InventoryConferenceStatus::Divergent]);
                })
                ->where('product_id', $item->product_id)
                ->sum('quantity_received');

            $pending = (float) $item->quantity - $receivedSum;
            $balances[$item->product_id] = max(0.0, $pending);
        }

        return $balances;
    }

    /**
     * Cria uma nova conferência física de recebimento para o saldo pendente do pedido.
     */
    public function createConference(PurchaseOrder $order, User $user): InventoryConference
    {
        if (!in_array($order->status, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived])) {
            throw ValidationException::withMessages([
                'status' => 'Apenas pedidos enviados ou parcialmente recebidos podem ter conferências de recebimento criadas.',
            ]);
        }

        // Verifica se já existe uma conferência pendente aberta para este pedido
        $hasPending = $order->inventoryConferences()->where('status', InventoryConferenceStatus::Pending)->exists();
        if ($hasPending) {
            throw ValidationException::withMessages([
                'status' => 'Já existe uma conferência de recebimento pendente em aberto para este pedido de compra.',
            ]);
        }

        $pendingBalances = $this->getPendingBalances($order);

        // Se não há saldo pendente, não há o que conferir
        if (array_sum($pendingBalances) <= 0) {
            throw ValidationException::withMessages([
                'status' => 'Todos os itens deste pedido já foram totalmente recebidos.',
            ]);
        }

        return DB::transaction(function () use ($order, $user, $pendingBalances) {
            // Cria a conferência
            $conference = InventoryConference::create([
                'purchase_order_id' => $order->id,
                'status'            => InventoryConferenceStatus::Pending,
                'checked_by'        => $user->id,
                'notes'             => null,
            ]);

            // Adiciona os itens da conferência baseados no saldo pendente
            foreach ($order->items as $item) {
                $pending = $pendingBalances[$item->product_id] ?? 0.0;
                if ($pending > 0) {
                    InventoryConferenceItem::create([
                        'inventory_conference_id' => $conference->id,
                        'product_id'              => $item->product_id,
                        'quantity_ordered'        => $pending, // O que estamos esperando nesta entrega
                        'quantity_received'       => 0.0, // A conferir
                    ]);
                }
            }

            return $conference;
        });
    }

    /**
     * Finaliza a conferência de inventário e realiza as movimentações físicas no estoque.
     *
     * @param InventoryConference $conference
     * @param User $user
     * @param array $counts Array de [product_id => quantity_received]
     * @param string|null $notes
     */
    public function completeConference(InventoryConference $conference, User $user, array $counts, ?string $notes = null): InventoryConference
    {
        if ($conference->isCompleted()) {
            throw ValidationException::withMessages([
                'status' => 'Esta conferência de recebimento já está finalizada.',
            ]);
        }

        $order = $conference->purchaseOrder;

        return DB::transaction(function () use ($conference, $order, $user, $counts, $notes) {
            // Locks
            $lockedOrder = PurchaseOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();
            $lockedConf  = InventoryConference::where('id', $conference->id)->lockForUpdate()->firstOrFail();

            $hasDivergences = false;
            $stockService = app(StockMovementService::class);

            foreach ($lockedConf->items as $confItem) {
                $productId = $confItem->product_id;
                $received  = (float) ($counts[$confItem->id] ?? $counts[$productId] ?? 0.0);
                $ordered   = (float) $confItem->quantity_ordered;

                // Atualiza o item da conferência
                $confItem->quantity_received = $received;
                $confItem->save();

                if (abs($received - $ordered) > 0.0001) {
                    $hasDivergences = true;
                }

                // Se recebeu quantidades físicas, dá entrada no estoque
                if ($received > 0) {
                    // Busca custo histórico no Pedido de Compra
                    $orderItem = PurchaseOrderItem::where('purchase_order_id', $lockedOrder->id)
                        ->where('product_id', $productId)
                        ->first();
                    $unitCost = $orderItem ? (float) $orderItem->unit_cost : (float) $confItem->product->cost_price;

                    $stockService->move(
                        $confItem->product,
                        $received,
                        StockMovementType::Input,
                        StockMovementSource::InventoryConference,
                        $lockedConf->id,
                        $user,
                        "Entrada física por conferência de Pedido de Compra {$lockedOrder->code}",
                        $unitCost
                    );
                }
            }

            // Define status da conferência
            $lockedConf->status = $hasDivergences ? InventoryConferenceStatus::Divergent : InventoryConferenceStatus::Completed;
            $lockedConf->notes = $notes;
            $lockedConf->completed_at = now();
            $lockedConf->checked_by = $user->id;
            $lockedConf->save();

            // Recalcula o status do Pedido de Compra baseado em todo o histórico de recebimento
            $pendingBalances = $this->getPendingBalances($lockedOrder);
            $totalPending = array_sum($pendingBalances);

            if ($totalPending <= 0) {
                // Totalmente recebido
                $lockedOrder->status = PurchaseOrderStatus::Received;
            } else {
                // Parcialmente recebido
                $lockedOrder->status = PurchaseOrderStatus::PartiallyReceived;
            }
            $lockedOrder->save();

            // Geração automática do Contas a Pagar
            if ($lockedOrder->status === PurchaseOrderStatus::Received) {
                $snapshot = [
                    'document_number' => $lockedOrder->code,
                    'supplier_name' => $lockedOrder->supplier->name ?? 'Fornecedor Avulso',
                    'total_amount' => (float) $lockedOrder->total_amount,
                ];

                $company = \App\Models\Company::first();
                if (!$company) {
                    $company = \App\Models\Company::create([
                        'id' => 1,
                        'name' => 'Neksa ERP',
                    ]);
                }
                $companyId = $company->id;

                app(\App\Services\FinancialService::class)->createPayable([
                    'company_id' => $companyId,
                    'supplier_id' => $lockedOrder->supplier_id,
                    'source_type' => get_class($lockedOrder),
                    'source_id' => $lockedOrder->id,
                    'source_snapshot' => $snapshot,
                    'competence_date' => now(),
                    'description' => "Contas a pagar gerado pelo recebimento do Pedido de Compra {$lockedOrder->code}",
                    'total_amount' => (float) $lockedOrder->total_amount,
                ], [
                    [
                        'installment_number' => 1,
                        'due_date' => now()->toDateString(),
                        'amount' => (float) $lockedOrder->total_amount,
                    ]
                ], $user);
            }

            return $lockedConf;
        });
    }

    /**
     * Cancela o Pedido de Compra (só se estiver em rascunho ou emitido sem nenhuma conferência concluída).
     */
    public function cancel(PurchaseOrder $order, User $user): PurchaseOrder
    {
        if ($order->status === PurchaseOrderStatus::Cancelled) {
            throw ValidationException::withMessages([
                'status' => 'Este pedido de compra já está cancelado.',
            ]);
        }

        if ($order->status === PurchaseOrderStatus::Received) {
            throw ValidationException::withMessages([
                'status' => 'Não é possível cancelar um pedido de compra totalmente recebido.',
            ]);
        }

        // Verifica se há alguma conferência que já deu entrada no estoque
        $hasCompletedConf = $order->inventoryConferences()
            ->whereIn('status', [InventoryConferenceStatus::Completed, InventoryConferenceStatus::Divergent])
            ->exists();

        if ($hasCompletedConf) {
            throw ValidationException::withMessages([
                'status' => 'Não é possível cancelar este pedido pois já possui recebimentos físicos registrados no estoque. Faça os ajustes manuais correspondentes.',
            ]);
        }

        $payable = \App\Models\Payable::where('source_type', get_class($order))
            ->where('source_id', $order->id)
            ->where('status', '!=', \App\Enums\PaymentStatus::Cancelled)
            ->first();

        if ($payable) {
            $hasPaidAmount = $payable->installments()->where('paid_amount', '>', 0.00)->exists();
            if ($hasPaidAmount) {
                throw ValidationException::withMessages([
                    'status' => 'Não é possível cancelar este documento porque existem movimentações financeiras já liquidadas. Realize o estorno financeiro antes de prosseguir.',
                ]);
            }
        }

        return DB::transaction(function () use ($order, $user, $payable) {
            // Cancela conferências pendentes
            $order->inventoryConferences()->where('status', InventoryConferenceStatus::Pending)->delete();

            $order->status = PurchaseOrderStatus::Cancelled;
            $order->save();

            if ($payable) {
                app(\App\Services\FinancialService::class)->cancelPayable($payable, $user);
            }

            return $order;
        });
    }
}
