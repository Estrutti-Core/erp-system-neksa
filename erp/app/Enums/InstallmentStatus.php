<?php

namespace App\Enums;

enum InstallmentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Pendente',
            self::Paid => 'Pago',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending => 'amber',
            self::Paid => 'green',
            self::Cancelled => 'red',
        };
    }
}
