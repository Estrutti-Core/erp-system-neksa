<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Pendente',
            self::PartiallyPaid => 'Parcialmente Pago',
            self::Paid => 'Pago',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending => 'amber',
            self::PartiallyPaid => 'blue',
            self::Paid => 'green',
            self::Cancelled => 'red',
        };
    }
}
