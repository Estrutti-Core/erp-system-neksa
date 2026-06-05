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

    public function questions(): HasMany
    {
        return $this->hasMany(ChecklistQuestion::class)->orderBy('order', 'asc');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_type_checklists');
    }

    public function serviceOrderChecklists(): HasMany
    {
        return $this->hasMany(ServiceOrderChecklist::class);
    }
}
