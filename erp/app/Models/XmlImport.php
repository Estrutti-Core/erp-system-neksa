<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class XmlImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'access_key',
        'supplier_id',
        'total_amount',
        'status',
        'imported_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'imported_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(XmlImportItem::class);
    }
}
