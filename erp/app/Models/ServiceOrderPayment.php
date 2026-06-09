<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_order_id',
        'payment_method',
        'amount',
        'installments_count',
        'first_due_date',
        'financial_account_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'installments_count' => 'integer',
        'first_due_date' => 'date',
    ];

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }
}
