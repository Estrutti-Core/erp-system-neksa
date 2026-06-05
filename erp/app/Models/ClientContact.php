<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'email',
        'phone',
        'whatsapp',
        'role',
        'is_primary',
        'is_phone_blocked',
        'is_whatsapp_blocked',
        'is_email_blocked',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_phone_blocked' => 'boolean',
        'is_whatsapp_blocked' => 'boolean',
        'is_email_blocked' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
