<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Pix = 'pix';
    case Boleto = 'boleto';
    case CreditCard = 'credit_card';
    case DebitCard = 'debit_card';
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Other = 'other';

    public function label(): string
    {
        return match($this) {
            self::Pix => 'Pix',
            self::Boleto => 'Boleto',
            self::CreditCard => 'Cartao de Credito',
            self::DebitCard => 'Cartao de Debito',
            self::Cash => 'Dinheiro',
            self::BankTransfer => 'Transferencia Bancaria',
            self::Other => 'Outros',
        };
    }
}
