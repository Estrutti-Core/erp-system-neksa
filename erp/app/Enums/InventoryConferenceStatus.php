<?php

namespace App\Enums;

enum InventoryConferenceStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Divergent = 'divergent';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Pendente',
            self::Completed => 'Sem Divergências',
            self::Divergent => 'Com Divergências',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending => 'amber',
            self::Completed => 'green',
            self::Divergent => 'red',
        };
    }
}
