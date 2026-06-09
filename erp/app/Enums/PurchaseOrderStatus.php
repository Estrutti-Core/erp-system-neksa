<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Rascunho',
            self::Ordered => 'Enviado ao Fornecedor',
            self::PartiallyReceived => 'Recebido Parcial',
            self::Received => 'Recebido Total',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft => 'gray',
            self::Ordered => 'blue',
            self::PartiallyReceived => 'amber',
            self::Received => 'green',
            self::Cancelled => 'red',
        };
    }
}
