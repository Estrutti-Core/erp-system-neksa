<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Converted = 'converted';

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Rascunho',
            self::Sent => 'Enviado',
            self::Approved => 'Aprovado',
            self::Rejected => 'Recusado',
            self::Converted => 'Convertido',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft => 'slate',
            self::Sent => 'blue',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Converted => 'violet',
        };
    }
}
