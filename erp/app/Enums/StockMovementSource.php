<?php

namespace App\Enums;

enum StockMovementSource: string
{
    case ServiceOrder = 'service_order';
    case Sale = 'sale';
    case PurchaseOrder = 'purchase_order';
    case Manual = 'manual';
    case InventoryConference = 'inventory_conference';

    public function label(): string
    {
        return match($this) {
            self::ServiceOrder => 'Ordem de Serviço',
            self::Sale => 'Venda',
            self::PurchaseOrder => 'Pedido de Compra',
            self::Manual => 'Ajuste Manual',
            self::InventoryConference => 'Conferência de Recebimento',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::ServiceOrder => 'blue',
            self::Sale => 'green',
            self::PurchaseOrder => 'indigo',
            self::Manual => 'amber',
            self::InventoryConference => 'purple',
        };
    }
}
