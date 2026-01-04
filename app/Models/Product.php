<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'sku',
        'barcode',
        'name',
        'category_id',
        'supplier_id',
        'cost_price',
        'sale_price',
        'stock_balance',
        'min_stock',
        'unit',
        'active',
        'metadata',
    ];

    protected $casts = [
        'active' => 'boolean',
        'cost_price' => 'decimal:4',
        'sale_price' => 'decimal:4',
        'stock_balance' => 'decimal:4',
        'min_stock' => 'decimal:4',
        'metadata' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
