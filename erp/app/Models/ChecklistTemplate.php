<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChecklistTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(ChecklistSection::class)->orderBy('order');
    }

    /** Perguntas sem seção (templates legados ou perguntas avulsas). */
    public function questions(): HasMany
    {
        return $this->hasMany(ChecklistQuestion::class)->orderBy('order', 'asc');
    }

    /** Todas as perguntas ordenadas por seção → ordem. */
    public function allQuestions(): HasMany
    {
        return $this->hasMany(ChecklistQuestion::class)->orderBy('order');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_type_checklists');
    }

    public function serviceOrderChecklists(): HasMany
    {
        return $this->hasMany(ServiceOrderChecklist::class);
    }

    public function hassSections(): bool
    {
        return $this->sections()->exists();
    }
}
