<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_id',
        'product_id',
        'service_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'total_price',
        'type',
    ];

    protected $casts = [
        'quantity'    => 'decimal:3',
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
        'type'        => ProductType::class,
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function isService(): bool
    {
        return $this->type === ProductType::Service;
    }

    public function isProduct(): bool
    {
        return $this->type === ProductType::Product;
    }

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->total_price = round($item->quantity * $item->unit_price, 2);
        });
    }
}
