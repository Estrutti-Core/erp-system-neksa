<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'installments_count',
        'interval_days',
        'is_active',
        'default_payment_method',
        'default_financial_account_id',
    ];

    protected $casts = [
        'installments_count' => 'integer',
        'interval_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function defaultFinancialAccount()
    {
        return $this->belongsTo(FinancialAccount::class, 'default_financial_account_id');
    }
}
