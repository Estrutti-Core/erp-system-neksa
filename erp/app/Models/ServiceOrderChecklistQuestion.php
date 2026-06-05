<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Snapshot imutável de uma pergunta de checklist instanciada em uma OS.
 *
 * ADR-004: Ao criar um ServiceOrderChecklist, todas as perguntas do template
 * são copiadas para esta tabela. A OS nunca mais lê diretamente do template,
 * garantindo que edições futuras no template não afetem OS já criadas.
 */
class ServiceOrderChecklistQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_order_checklist_id',
        'checklist_question_id',
        'question_text',
        'question_type',
        'options_json',
        'is_required',
        'order',
    ];

    protected $casts = [
        'is_required'  => 'boolean',
        'order'        => 'integer',
        'options_json' => 'array',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(ServiceOrderChecklist::class, 'service_order_checklist_id');
    }

    /**
     * Referência opcional ao template original (apenas para rastreabilidade).
     */
    public function originalQuestion(): BelongsTo
    {
        return $this->belongsTo(ChecklistQuestion::class, 'checklist_question_id')->withTrashed();
    }

    public function answer(): HasOne
    {
        return $this->hasOne(ChecklistAnswer::class, 'service_order_checklist_question_id');
    }

    public function getOptionsAttribute(): array
    {
        return $this->options_json ?? [];
    }

    public function isAnswered(): bool
    {
        return $this->answer()->exists();
    }
}
