<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Pendente',
            self::Completed => 'Faturada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending => 'amber',
            self::Completed => 'green',
            self::Cancelled => 'red',
        };
    }
}
