<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'client_id',
        'client_address_id',
        'quote_id',
        'status',
        'discount_amount',
        'items_amount',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'status'          => SaleStatus::class,
        'discount_amount' => 'decimal:2',
        'items_amount'    => 'decimal:2',
        'total_amount'    => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function clientAddress(): BelongsTo
    {
        return $this->belongsTo(ClientAddress::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'ilike', "%{$term}%")
                ->orWhereHas('client', fn ($cq) => $cq->where('name', 'ilike', "%{$term}%"));
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function recalculateTotals(): void
    {
        $this->items_amount = $this->items()->sum('total_price');
        $this->total_amount = max(0, $this->items_amount - $this->discount_amount);
        $this->save();
    }

    protected static function booted(): void
    {
        static::creating(function (self $sale) {
            if (!$sale->code) {
                $lastSale = self::orderBy('id', 'desc')->first();
                $nextNumber = $lastSale ? ((int) preg_replace('/[^0-9]/', '', $lastSale->code)) + 1 : 1;
                $sale->code = 'VEN-' . date('Y') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
