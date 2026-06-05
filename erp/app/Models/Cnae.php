<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Cnae extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
    ];

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_cnaes')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
