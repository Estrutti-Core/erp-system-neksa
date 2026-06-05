<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'document',
        'phone',
        'email',
        'address',
        'logo_path',
        'primary_color',
    ];
}
