<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ChecklistAnswer extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'service_order_checklist_id',
        'checklist_question_id',
        'service_order_checklist_question_id',
        'answer_value',
        'photo_path',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('checklist_answer');
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(ServiceOrderChecklist::class, 'service_order_checklist_id');
    }

    /**
     * Pergunta instanciada (snapshot — fonte primária de leitura).
     */
    public function instancedQuestion(): BelongsTo
    {
        return $this->belongsTo(ServiceOrderChecklistQuestion::class, 'service_order_checklist_question_id');
    }

    /**
     * Pergunta original do template (apenas para retrocompatibilidade).
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(ChecklistQuestion::class, 'checklist_question_id')->withTrashed();
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }
}
