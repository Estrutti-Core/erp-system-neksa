<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryConferenceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_conference_id',
        'product_id',
        'quantity_ordered',
        'quantity_received',
    ];

    protected $casts = [
        'quantity_ordered'  => 'decimal:3',
        'quantity_received' => 'decimal:3',
    ];

    public function inventoryConference(): BelongsTo
    {
        return $this->belongsTo(InventoryConference::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
