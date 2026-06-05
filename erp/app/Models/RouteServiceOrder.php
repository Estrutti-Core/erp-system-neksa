<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteServiceOrder extends Model
{
    protected $fillable = [
        'route_id',
        'service_order_id',
        'sequence',
        'distance_from_prev_km',
        'estimated_minutes_from_prev',
        'estimated_arrival_at',
    ];

    protected $casts = [
        'distance_from_prev_km'      => 'float',
        'estimated_arrival_at'       => 'datetime',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }
}
