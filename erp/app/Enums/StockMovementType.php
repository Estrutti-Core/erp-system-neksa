<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Input = 'input';
    case Output = 'output';

    public function label(): string
    {
        return match($this) {
            self::Input => 'Entrada',
            self::Output => 'Saída',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Input => 'green',
            self::Output => 'red',
        };
    }
}
