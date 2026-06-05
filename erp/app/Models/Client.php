<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'social_name',
        'trade_name',
        'document',
        'document_type',
        'phone',
        'phone_secondary',
        'email',
        'notes',
        'sector',
        'opening_date',
        'capital_social',
        'company_size',
        'legal_nature',
        'registration_status',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'opening_date' => 'date',
        'capital_social' => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function addresses(): HasMany
    {
        return $this->hasMany(ClientAddress::class);
    }

    public function primaryAddress(): HasOne
    {
        return $this->hasOne(ClientAddress::class)->where('is_primary', true);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function cnaes(): BelongsToMany
    {
        return $this->belongsToMany(Cnae::class, 'client_cnaes')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(ClientEquipment::class);
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(ClientContact::class)->where('is_primary', true);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getFormattedDocumentAttribute(): string
    {
        if (! $this->document) {
            return '—';
        }

        if ($this->document_type === 'cpf') {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $this->document);
        }

        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $this->document);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
                ->orWhere('document', 'like', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        });
    }
}
