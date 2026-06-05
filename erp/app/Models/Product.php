<?php

namespace App\Models;

use App\Enums\FiscalOrigin;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'sku',
        'barcode',
        'ncm',
        'cfop',
        'cst',
        'csosn',
        'fiscal_origin',
        'commercial_unit',
        'taxable_unit',
        'cost_price',
        'sale_price',
        'stock',
        'category_id',
        'is_active',
        'internal_notes',
        'type',
        'is_stock_controlled',
    ];

    protected $casts = [
        'fiscal_origin'       => FiscalOrigin::class,
        'type'                => ProductType::class,
        'cost_price'          => 'decimal:2',
        'sale_price'          => 'decimal:2',
        'stock'               => 'decimal:3',
        'is_active'           => 'boolean',
        'is_stock_controlled' => 'boolean',
    ];

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeProductsOnly($query)
    {
        return $query->where('type', ProductType::Product->value);
    }

    public function scopeServicesOnly($query)
    {
        return $query->where('type', ProductType::Service->value);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
                ->orWhere('sku', 'ilike', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%");
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isService(): bool
    {
        return $this->type === ProductType::Service;
    }

    public function isProduct(): bool
    {
        return $this->type === ProductType::Product;
    }
}
