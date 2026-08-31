<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ações disparadas automaticamente quando uma pergunta é respondida.
 * Fase 2: scaffolding já presente no schema para não exigir refatoração futura.
 */
class ChecklistQuestionTrigger extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklist_question_id',
        'trigger_condition',  // eq_yes | eq_no | any_value | photo_uploaded
        'action_type',        // notify_admin | flag_os | create_task
        'action_payload',     // JSON livre: { "message": "...", "role": "admin" }
    ];

    protected $casts = [
        'action_payload' => 'array',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(ChecklistQuestion::class, 'checklist_question_id');
    }
}
