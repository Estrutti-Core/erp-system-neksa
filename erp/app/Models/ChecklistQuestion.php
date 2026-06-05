<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ChecklistQuestion extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'checklist_template_id',
        'question_text',
        'question_type', // text, checkbox, select, photo, drawing, label
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

    /**
     * Retorna as opções formatadas para uso em selects HTML.
     */
    public function getOptionsAttribute(): array
    {
        return $this->options_json ?? [];
    }

    /**
     * Tipos de pergunta válidos.
     */
    public static function questionTypes(): array
    {
        return [
            'text'     => 'Texto Livre',
            'checkbox' => 'Checkbox (Sim/Não)',
            'select'   => 'Seleção de Opções',
            'photo'    => 'Foto',
            'drawing'  => 'Desenho / Esboço',
            'label'    => 'Rótulo Informativo',
        ];
    }
}
