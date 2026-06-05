<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'service_order_status_history';

    protected $fillable = [
        'service_order_id',
        'from_status_id',
        'to_status_id',
        'changed_by',
        'entered_at',
        'left_at',
        'duration_minutes',
        'notes',
        'sla_alert_sent',
    ];

    protected $casts = [
        'entered_at' => 'datetime',
        'left_at' => 'datetime',
        'duration_minutes' => 'integer',
        'sla_alert_sent' => 'boolean',
    ];

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(ServiceOrderStatus::class, 'from_status_id')->withTrashed();
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(ServiceOrderStatus::class, 'to_status_id')->withTrashed();
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
