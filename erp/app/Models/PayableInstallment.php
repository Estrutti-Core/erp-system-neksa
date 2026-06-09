<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use Carbon\Carbon;

class PayableInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payable_id',
        'installment_number',
        'due_date',
        'amount',
        'discount_amount',
        'interest_amount',
        'paid_amount',
        'net_amount',
        'paid_at',
        'payment_method',
        'status',
        'financial_account_id',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'payment_method' => PaymentMethod::class,
        'status' => InstallmentStatus::class,
        'financial_account_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Getter dinâmico para o status da parcela, incorporando atraso.
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->status === InstallmentStatus::Pending && Carbon::parse($this->due_date)->isBefore(Carbon::today())) {
            return 'overdue';
        }
        return $this->status->value;
    }

    public function isOverdue(): bool
    {
        return $this->status === InstallmentStatus::Pending && Carbon::parse($this->due_date)->isBefore(Carbon::today());
    }
}
