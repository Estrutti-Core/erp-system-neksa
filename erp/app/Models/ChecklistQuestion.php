<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ChecklistQuestion extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'checklist_template_id',
        'checklist_section_id',
        'source_block_id',
        'question_text',
        'question_type', // text, checkbox, select, photo, drawing, label, signature
        'options_json',
        'is_required',
        'order',
    ];

    protected $casts = [
        'is_required'  => 'boolean',
        'order'        => 'integer',
        'options_json' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('checklist_question');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ChecklistSection::class, 'checklist_section_id')->withTrashed();
    }

    public function sourceBlock(): BelongsTo
    {
        return $this->belongsTo(ChecklistBlock::class, 'source_block_id')->withTrashed();
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(ChecklistQuestionTrigger::class);
    }

    /**
     * Retorna as opções formatadas para uso em selects HTML.
     */
    public function getOptionsAttribute(): array
    {
        return $this->options_json ?? [];
    }

    public static function questionTypes(): array
    {
        return [
            'text'      => 'Texto Livre',
            'checkbox'  => 'Checkbox (Sim/Não)',
            'select'    => 'Seleção de Opções',
            'photo'     => 'Foto',
            'drawing'   => 'Desenho / Esboço',
            'label'     => 'Rótulo Informativo',
            'signature' => 'Assinatura Digital',
        ];
    }
}
