<?php

namespace App\Enums;

enum ServiceOrderPriority: string
{
    case Low    = 'low';
    case Normal = 'normal';
    case High   = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match($this) {
            self::Low    => 'Baixa',
            self::Normal => 'Normal',
            self::High   => 'Alta',
            self::Urgent => 'Urgente',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Low    => 'slate',
            self::Normal => 'blue',
            self::High   => 'amber',
            self::Urgent => 'red',
        };
    }

    public static function options(): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], self::cases());
    }
}
