<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ServiceOrderChecklist extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'service_order_id',
        'checklist_template_id',
        'filled_by',
        'filled_at',
        'is_inactive',
    ];

    protected $casts = [
        'filled_at'   => 'datetime',
        'is_inactive' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('service_order_checklist');
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id')->withTrashed();
    }

    public function filledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filled_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ChecklistAnswer::class);
    }

    /**
     * Perguntas instanciadas (snapshot imutável do template no momento da criação).
     */
    public function instancedQuestions(): HasMany
    {
        return $this->hasMany(ServiceOrderChecklistQuestion::class)->orderBy('order');
    }

    public function isFilled(): bool
    {
        return !is_null($this->filled_at);
    }

    /**
     * Verifica se todas as perguntas obrigatórias da instância foram respondidas.
     */
    public function hasAllRequiredAnswers(): bool
    {
        return !$this->instancedQuestions()
            ->where('is_required', true)
            ->whereDoesntHave('answer')
            ->exists();
    }
}
