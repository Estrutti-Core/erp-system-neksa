<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * ADR-006: Modelagem explícita de check-in para rastreabilidade operacional.
 * Suporta eventos de checkin e checkout, viabilizando cálculo de tempo em campo.
 */
class ServiceOrderCheckin extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'service_order_id',
        'user_id',
        'type',
        'latitude',
        'longitude',
        'notes',
        'checked_at',
    ];

    protected $casts = [
        'latitude'   => 'float',
        'longitude'  => 'float',
        'checked_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->dontSubmitEmptyLogs()
            ->useLogName('service_order_checkin');
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCheckin(): bool
    {
        return $this->type === 'checkin';
    }

    public function isCheckout(): bool
    {
        return $this->type === 'checkout';
    }
}
