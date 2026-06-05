<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderHistory extends Model
{
    use HasFactory;

    protected $table = 'service_order_history';

    protected $fillable = [
        'service_order_id',
        'user_id',
        'event',
        'from_status',
        'to_status',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getEventLabelAttribute(): string
    {
        return match($this->event) {
            'created'            => 'OS criada',
            'status_changed'     => 'Status alterado',
            'technician_assigned'=> 'Técnico atribuído',
            'checkin'            => 'Check-in realizado',
            'note_added'         => 'Observação adicionada',
            'photo_uploaded'     => 'Foto adicionada',
            'item_added'         => 'Item adicionado',
            'signature_collected'=> 'Assinatura coletada',
            'completed'          => 'OS finalizada',
            'cancelled'          => 'OS cancelada',
            default              => ucfirst(str_replace('_', ' ', $this->event)),
        };
    }
}
