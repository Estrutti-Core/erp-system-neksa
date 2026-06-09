<?php

namespace App\Models;

use App\Enums\InventoryConferenceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class InventoryConference extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'purchase_order_id',
        'status',
        'checked_by',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'status'       => InventoryConferenceStatus::class,
        'completed_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->dontSubmitEmptyLogs()
            ->useLogName('inventory_conference');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryConferenceItem::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === InventoryConferenceStatus::Completed || $this->status === InventoryConferenceStatus::Divergent;
    }
}
