<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Snapshot imutável de uma seção de checklist instanciada em uma OS.
 *
 * ADR-004 (extendido): Criada junto com ServiceOrderChecklist.
 * Edições futuras nas seções do template não afetam OS já abertas.
 */
class ServiceOrderChecklistSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_order_checklist_id',
        'checklist_section_id',
        'title',
        'description',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(ServiceOrderChecklist::class, 'service_order_checklist_id');
    }

    public function originalSection(): BelongsTo
    {
        return $this->belongsTo(ChecklistSection::class, 'checklist_section_id')->withTrashed();
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ServiceOrderChecklistQuestion::class, 'service_order_checklist_section_id')
            ->orderBy('order');
    }
}
