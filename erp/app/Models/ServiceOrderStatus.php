<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceOrderStatus extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'service_order_statuses';

    protected $fillable = [
        'slug',
        'name',
        'color',
        'is_system',
        'is_open_state',
        'is_completed_state',
        'is_cancelled_state',
        'expected_time_minutes',
        'max_stay_minutes',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_open_state' => 'boolean',
        'is_completed_state' => 'boolean',
        'is_cancelled_state' => 'boolean',
        'expected_time_minutes' => 'integer',
        'max_stay_minutes' => 'integer',
    ];

    /**
     * Transições permitidas a partir deste status.
     */
    public function allowedTransitions(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'service_order_status_transitions',
            'from_status_id',
            'to_status_id'
        );
    }

    /**
     * Transições permitidas que chegam a este status.
     */
    public function incomingTransitions(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'service_order_status_transitions',
            'to_status_id',
            'from_status_id'
        );
    }

    /**
     * Verifica se é permitida a transição para o status de destino.
     */
    public function canTransitionTo($target): bool
    {
        $targetId = $target instanceof self ? $target->id : (int) $target;
        return $this->allowedTransitions()->where('to_status_id', $targetId)->exists();
    }
}
