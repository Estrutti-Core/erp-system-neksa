<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'sku',
        'cfop',
        'cst',
        'iss_rate',
        'iss_withheld',
        'pis_retention_rate',
        'cofins_retention_rate',
        'csll_retention_rate',
        'inss_retention_rate',
        'municipal_service_code',
        'price',
        'is_active',
    ];

    protected $casts = [
        'iss_rate' => 'decimal:2',
        'iss_withheld' => 'boolean',
        'pis_retention_rate' => 'decimal:2',
        'cofins_retention_rate' => 'decimal:2',
        'csll_retention_rate' => 'decimal:2',
        'inss_retention_rate' => 'decimal:2',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function checklistTemplates(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ChecklistTemplate::class, 'service_type_checklists');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
                ->orWhere('sku', 'ilike', "%{$term}%")
                ->orWhere('description', 'ilike', "%{$term}%");
        });
    }
}
