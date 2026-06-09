<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'code',
        'supplier_id',
        'status',
        'total_amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'status'       => PurchaseOrderStatus::class,
        'total_amount' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->dontSubmitEmptyLogs()
            ->useLogName('purchase_order');
    }

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            if (!$order->code) {
                $lastOrder = self::orderBy('id', 'desc')->first();
                $nextNumber = $lastOrder ? ((int) preg_replace('/[^0-9]/', '', $lastOrder->code)) + 1 : 1;
                $order->code = 'PC-' . date('Y') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inventoryConferences(): HasMany
    {
        return $this->hasMany(InventoryConference::class);
    }

    public function recalculateTotal(): void
    {
        $this->total_amount = $this->items()->sum('total_cost');
        $this->save();
    }
}
