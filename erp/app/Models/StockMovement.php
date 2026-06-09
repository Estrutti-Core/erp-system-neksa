<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Enums\StockMovementSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'warehouse_id',
        'quantity',
        'stock_before',
        'stock_after',
        'unit_cost',
        'type',
        'source_type',
        'source_id',
        'notes',
    ];

    protected $casts = [
        'quantity'     => 'decimal:3',
        'stock_before' => 'decimal:3',
        'stock_after'  => 'decimal:3',
        'unit_cost'    => 'decimal:2',
        'type'         => StockMovementType::class,
        'source_type'  => StockMovementSource::class,
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    protected static function booted(): void
    {
        static::updating(function ($model) {
            throw new \Exception("Movimentações de estoque são imutáveis e não podem ser alteradas.");
        });

        static::deleting(function ($model) {
            throw new \Exception("Movimentações de estoque são imutáveis e não podem ser excluídas.");
        });
    }
}
