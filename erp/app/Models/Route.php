<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    use HasFactory;

    protected $fillable = [
        'technician_id',
        'created_by',
        'name',
        'route_date',
        'status',
        'total_distance_km',
        'estimated_minutes',
        'notes',
        'optimized_order',
    ];

    protected $casts = [
        'route_date'        => 'date',
        'total_distance_km' => 'float',
        'optimized_order'   => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function serviceOrders(): BelongsToMany
    {
        return $this->belongsToMany(ServiceOrder::class, 'route_service_orders')
            ->withPivot(['sequence', 'distance_from_prev_km', 'estimated_minutes_from_prev', 'estimated_arrival_at'])
            ->withTimestamps()
            ->orderByPivot('sequence');
    }

    public function routeServiceOrders(): HasMany
    {
        return $this->hasMany(RouteServiceOrder::class)->orderBy('sequence');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function getFormattedDateAttribute(): string
    {
        return $this->route_date->format('d/m/Y');
    }

    public function getEstimatedDurationAttribute(): string
    {
        if (! $this->estimated_minutes) {
            return '—';
        }
        $h = intdiv($this->estimated_minutes, 60);
        $m = $this->estimated_minutes % 60;

        return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
    }
}
