<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientEquipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'client_equipments';

    protected $fillable = [
        'client_id',
        'name',
        'brand',
        'model',
        'serial_number',
        'notes',
    ];

    /**
     * Get the client that owns the equipment.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the service orders for the equipment.
     */
    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'equipment_id');
    }
}
