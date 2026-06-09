<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'description',
        'quantity',
        'unit',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'quantity'   => 'decimal:3',
        'unit_cost'  => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->total_cost = round($item->quantity * $item->unit_cost, 2);
        });
    }
}
