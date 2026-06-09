<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinancialEvent extends Model
{
    use HasFactory;

    // Apenas inserção é permitida
    public $timestamps = false;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'event_type',
        'old_data',
        'new_data',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Enforça imutabilidade absoluta no log de auditoria financeira
        static::updating(function ($event) {
            throw new \Exception("Nao e permitido alterar logs de auditoria financeira.");
        });

        static::deleting(function ($event) {
            throw new \Exception("Nao e permitido excluir logs de auditoria financeira.");
        });
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
