<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'service_region',
        'notes',
        'is_active',
        'current_latitude',
        'current_longitude',
        'last_checkin_at',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'current_latitude'  => 'float',
        'current_longitude' => 'float',
        'last_checkin_at'   => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
