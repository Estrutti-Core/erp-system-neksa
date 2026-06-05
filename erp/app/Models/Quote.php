<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'client_id',
        'client_address_id',
        'equipment_id',
        'status',
        'type',
        'valid_until',
        'notes',
        'internal_notes',
        'discount_amount',
        'items_amount',
        'total_amount',
        'converted_at',
    ];

    protected $casts = [
        'status'          => QuoteStatus::class,
        'valid_until'     => 'date',
        'discount_amount' => 'decimal:2',
        'items_amount'    => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'converted_at'    => 'datetime',
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

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ClientEquipment::class, 'equipment_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
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

    public function isConverted(): bool
    {
        return $this->status === QuoteStatus::Converted;
    }

    public function recalculateTotals(): void
    {
        $this->items_amount = $this->items()->sum('total_price');
        $this->total_amount = max(0, $this->items_amount - $this->discount_amount);
        $this->save();
    }

    protected static function booted(): void
    {
        static::creating(function (self $quote) {
            if (!$quote->code) {
                $lastQuote = self::orderBy('id', 'desc')->first();
                $nextNumber = $lastQuote ? ((int) preg_replace('/[^0-9]/', '', $lastQuote->code)) + 1 : 1;
                $quote->code = 'ORC-' . date('Y') . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
