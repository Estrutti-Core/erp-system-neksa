<?php

namespace App\Models;

use App\Models\ServiceOrderStatus;
use App\Models\ServiceOrderStatusHistory;
use App\Enums\ServiceOrderPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ServiceOrderCheckin;

class ServiceOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted()
    {
        static::creating(function ($serviceOrder) {
            if (!$serviceOrder->status_id) {
                $openStatus = ServiceOrderStatus::where('slug', 'open')->first();
                if ($openStatus) {
                    $serviceOrder->status_id = $openStatus->id;
                }
            }
        });
    }

    protected $fillable = [
        'code',
        'client_id',
        'client_address_id',
        'equipment_id',
        'quote_id',
        'technician_id',
        'created_by',
        'status_id',
        'priority',
        'description',
        'services_performed',
        'internal_notes',
        'total_amount',
        'service_amount',
        'parts_amount',
        'scheduled_at',
        'started_at',
        'completed_at',
        'checkin_latitude',
        'checkin_longitude',
        'checkin_at',
    ];

    protected $casts = [
        'status_id'          => 'integer',
        'priority'           => ServiceOrderPriority::class,
        'total_amount'       => 'decimal:2',
        'service_amount'     => 'decimal:2',
        'parts_amount'       => 'decimal:2',
        'checkin_latitude'   => 'float',
        'checkin_longitude'  => 'float',
        'scheduled_at'       => 'datetime',
        'started_at'         => 'datetime',
        'completed_at'       => 'datetime',
        'checkin_at'         => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function status(): BelongsTo
    {
        return $this->belongsTo(ServiceOrderStatus::class, 'status_id')->withTrashed();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ServiceOrderStatusHistory::class)->orderBy('entered_at', 'asc');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ClientEquipment::class, 'equipment_id');
    }

    public function clientAddress(): BelongsTo
    {
        return $this->belongsTo(ClientAddress::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ServiceOrderAttachment::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(ServiceOrderChecklist::class);
    }

    /**
     * Checklists ativos (exclui os marcados como inativos por remoção de serviço).
     */
    public function activeChecklists(): HasMany
    {
        return $this->hasMany(ServiceOrderChecklist::class)->where('is_inactive', false);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(ServiceOrderCheckin::class)->orderBy('checked_at');
    }

    /**
     * Verifica se todos os checklists ativos foram preenchidos.
     */
    public function allChecklistsFilled(): bool
    {
        return !$this->activeChecklists()->whereNull('filled_at')->exists();
    }

    /**
     * Verifica se todas as perguntas obrigatórias de todos os checklists ativos foram respondidas.
     */
    public function allRequiredAnswersFilled(): bool
    {
        $activeChecklists = $this->activeChecklists()->with('instancedQuestions.answer')->get();

        foreach ($activeChecklists as $checklist) {
            if (!$checklist->hasAllRequiredAnswers()) {
                return false;
            }
        }

        return true;
    }

    public function hasSignature(): bool
    {
        return $this->signature()->exists();
    }

    public function hasCheckin(): bool
    {
        return $this->checkins()->where('type', 'checkin')->exists();
    }

    public function history(): HasMany
    {
        return $this->hasMany(ServiceOrderHistory::class)->orderBy('created_at', 'desc');
    }

    public function signature(): HasOne
    {
        return $this->hasOne(ServiceOrderSignature::class);
    }

    public function routeServiceOrders(): HasMany
    {
        return $this->hasMany(RouteServiceOrder::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeStatus($query, $status)
    {
        if (is_array($status)) {
            if (empty($status)) return $query;
            $first = reset($status);
            if (is_numeric($first)) {
                return $query->whereIn('status_id', $status);
            }
            return $query->whereHas('status', function ($q) use ($status) {
                $q->whereIn('slug', $status);
            });
        }

        if (is_numeric($status)) {
            return $query->where('status_id', $status);
        }

        return $query->whereHas('status', function ($q) use ($status) {
            $q->where('slug', $status);
        });
    }

    public function scopeForTechnician($query, int $userId)
    {
        return $query->where('technician_id', $userId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_at', today());
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'ilike', "%{$term}%")
                ->orWhere('description', 'ilike', "%{$term}%")
                ->orWhereHas('client', fn ($cq) => $cq->where('name', 'ilike', "%{$term}%"));
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return (bool) ($this->status?->is_open_state);
    }

    public function isCompleted(): bool
    {
        return (bool) ($this->status?->is_completed_state);
    }

    public function isCancelled(): bool
    {
        return (bool) ($this->status?->is_cancelled_state);
    }

    public function recalculateTotal(): void
    {
        $this->parts_amount   = $this->items()->where('type', 'part')->sum('total_price');
        $this->service_amount = $this->items()->where('type', 'service')->sum('total_price');
        $this->total_amount   = $this->service_amount + $this->parts_amount;
        $this->save();
    }
}
