<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChecklistBlockQuestion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'checklist_block_id',
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

    public function block(): BelongsTo
    {
        return $this->belongsTo(ChecklistBlock::class, 'checklist_block_id');
    }

    public function getOptionsAttribute(): array
    {
        return $this->options_json ?? [];
    }
}
