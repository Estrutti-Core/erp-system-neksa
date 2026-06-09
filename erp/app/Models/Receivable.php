<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Enums\PaymentStatus;
use App\Enums\InstallmentStatus;
use Illuminate\Validation\ValidationException;

class Receivable extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'company_id',
        'client_id',
        'code',
        'source_type',
        'source_id',
        'source_snapshot',
        'competence_date',
        'description',
        'total_amount',
        'discount_amount',
        'interest_amount',
        'net_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'source_snapshot' => 'array',
        'competence_date' => 'date',
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'status' => PaymentStatus::class,
    ];

    protected static function booted(): void
    {
        // Bloqueia deleção física e lógica de títulos financeiros
        static::deleting(function ($receivable) {
            throw new \Exception("Nao e permitido deletar registros financeiros do contas a receber por diretrizes de auditoria. Cancele o titulo se necessario.");
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->dontSubmitEmptyLogs()
            ->useLogName('receivable');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function installments(): HasMany
    {
        return $this->hasMany(ReceivableInstallment::class)->orderBy('installment_number');
    }
}
