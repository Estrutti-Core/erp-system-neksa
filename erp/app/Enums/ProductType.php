<?php

namespace App\Enums;

enum ProductType: string
{
    case Product = 'product';
    case Service = 'service';

    public function label(): string
    {
        return match($this) {
            self::Product => 'Produto',
            self::Service => 'Serviço',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Product => 'slate',
            self::Service => 'violet',
        };
    }
}
