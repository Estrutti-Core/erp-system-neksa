<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'document',
        'role',
        'email',
        'phone',
        'hire_date',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'hire_date' => 'date',
    ];
}
