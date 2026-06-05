<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * ADR-005: Modelagem explícita da assinatura digital do cliente.
 * Inclui campos de auditoria para rastreabilidade e evidência de aceite.
 */
class ServiceOrderSignature extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'service_order_id',
        'signer_name',
        'signer_document',
        'path',
        'disk',
        'signed_latitude',
        'signed_longitude',
        'ip_address',
        'user_agent',
        'signed_at',
    ];

    protected $casts = [
        'signed_latitude'  => 'float',
        'signed_longitude' => 'float',
        'signed_at'        => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('service_order_signature');
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
