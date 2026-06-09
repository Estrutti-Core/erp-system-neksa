<?php

namespace App\Actions;

use App\Enums\ProductType;
use App\Enums\QuoteStatus;
use App\Enums\SaleStatus;
use App\Enums\ServiceOrderPriority;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\ServiceOrderStatus;
use App\Models\ServiceOrderStatusHistory;
use App\Models\ServiceOrderHistory;
use Illuminate\Support\Facades\DB;
use Exception;

class ConvertQuoteAction
{
    /**
     * Converte um orçamento em Venda ou Ordem de Serviço.
     *
     * @param Quote $quote
     * @param string $destinationType ('sale' ou 'service_order')
     * @return Sale|ServiceOrder
     * @throws Exception
     */
    public function execute(Quote $quote, string $destinationType)
    {
        if ($quote->status === QuoteStatus::Converted) {
            throw new Exception("Este orçamento já foi convertido.");
        }

        return DB::transaction(function () use ($quote, $destinationType) {
            if ($destinationType === 'sale') {
                return $this->convertToSale($quote);
            } elseif ($destinationType === 'service_order') {
                return $this->convertToServiceOrder($quote);
            }

            throw new Exception("Tipo de destino inválido para conversão.");
        });
    }

    /**
     * Converte o orçamento para Venda (apenas produtos).
     */
    private function convertToSale(Quote $quote): Sale
    {
        // Regra de Negócio: Vendas possuem apenas produtos, não admitem serviços.
        $hasServices = $quote->items()->where('type', ProductType::Service->value)->exists();
        if ($hasServices) {
            throw new Exception("Não é possível converter em Venda um orçamento que contenha Serviços. Remova os serviços ou gere uma Ordem de Serviço.");
        }

        // Criar a Venda
        $sale = Sale::create([
            'client_id'         => $quote->client_id,
            'client_address_id' => $quote->client_address_id,
            'quote_id'          => $quote->id,
            'status'            => SaleStatus::Pending,
            'discount_amount'   => $quote->discount_amount,
            'items_amount'      => $quote->items_amount,
            'total_amount'      => $quote->total_amount,
            'notes'             => $quote->notes,
            'carrier'           => $quote->carrier,
            'freight_price'     => $quote->freight_price ?? 0.00,
            'freight_type'      => $quote->freight_type ?? 9,
            'volume'            => $quote->volume,
            'weight_gross'      => $quote->weight_gross,
            'weight_net'        => $quote->weight_net,
            'delivery_deadline' => $quote->delivery_deadline,
            'warranty'          => $quote->warranty,
            'validity'          => $quote->validity,
        ]);

        // Transferir os itens
        foreach ($quote->items as $item) {
            SaleItem::create([
                'sale_id'     => $sale->id,
                'product_id'  => $item->product_id,
                'description' => $item->description,
                'quantity'    => $item->quantity,
                'unit'        => $item->unit,
                'unit_price'  => $item->unit_price,
                'total_price' => $item->total_price,
            ]);
        }

        // Marcar orçamento como convertido
        $quote->update([
            'status'       => QuoteStatus::Converted,
            'type'         => 'sale',
            'converted_at' => now(),
        ]);

        return $sale;
    }

    /**
     * Converte o orçamento para Ordem de Serviço (serviço obrigatório).
     */
    private function convertToServiceOrder(Quote $quote): ServiceOrder
    {
        // Regra de Negócio: OS exige pelo menos um serviço
        $hasServices = $quote->items()->where('type', ProductType::Service->value)->exists();
        if (!$hasServices) {
            throw new Exception("Não é possível gerar uma Ordem de Serviço sem nenhum Serviço cadastrado. Adicione pelo menos um serviço ou gere uma Venda.");
        }

        // Calcular quantia de peças e serviços separadamente
        $serviceAmount = $quote->items()->where('type', ProductType::Service->value)->sum('total_price');
        $partsAmount   = $quote->items()->where('type', ProductType::Product->value)->sum('total_price');

        $year  = now()->year;
        $count = ServiceOrder::whereYear('created_at', $year)->count() + 1;
        $osCode = sprintf('OS-%s-%05d', $year, $count);

        $openStatus = ServiceOrderStatus::where('slug', 'open')->firstOrFail();

        // Criar a OS
        $serviceOrder = ServiceOrder::create([
            'code'              => $osCode,
            'client_id'         => $quote->client_id,
            'client_address_id' => $quote->client_address_id,
            'equipment_id'      => $quote->equipment_id,
            'quote_id'          => $quote->id,
            'status_id'         => $openStatus->id,
            'priority'          => ServiceOrderPriority::Normal,
            'description'       => $quote->notes ?? 'Ordem de serviço gerada a partir do orçamento ' . $quote->code,
            'total_amount'      => $quote->total_amount,
            'service_amount'    => $serviceAmount,
            'parts_amount'      => $partsAmount,
            'created_by'        => auth()->id() ?? 1,
        ]);

        // Criar histórico de status dedicado
        ServiceOrderStatusHistory::create([
            'service_order_id' => $serviceOrder->id,
            'from_status_id'   => null,
            'to_status_id'     => $openStatus->id,
            'changed_by'       => auth()->id() ?? 1,
            'entered_at'       => now(),
            'notes'            => 'Ordem de serviço gerada a partir de conversão de orçamento.',
        ]);

        // Criar histórico geral de timeline
        ServiceOrderHistory::create([
            'service_order_id' => $serviceOrder->id,
            'user_id'          => auth()->id() ?? 1,
            'event'            => 'created',
            'to_status'        => 'open',
            'description'      => 'Ordem de Serviço criada via conversão de orçamento.',
        ]);

        // Disparar evento
        event(new \App\Events\ServiceOrderEnteredStatus($serviceOrder, $openStatus, now()));

        // Transferir os itens
        foreach ($quote->items as $item) {
            ServiceOrderItem::create([
                'service_order_id' => $serviceOrder->id,
                'product_id'       => $item->product_id,
                'type'             => $item->isService() ? 'service' : 'part',
                'description'      => $item->description,
                'quantity'         => $item->quantity,
                'unit'             => $item->unit,
                'unit_price'       => $item->unit_price,
                'total_price'      => $item->total_price,
            ]);
        }

        // Marcar orçamento como convertido
        $quote->update([
            'status'       => QuoteStatus::Converted,
            'type'         => 'service_order',
            'converted_at' => now(),
        ]);

        // Sync required checklists for the converted Service Order
        (new \App\Services\ServiceOrderChecklistService())->syncRequiredChecklists($serviceOrder);

        return $serviceOrder;
    }
}
