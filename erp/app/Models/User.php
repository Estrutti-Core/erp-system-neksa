<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function technicianProfile(): HasOne
    {
        return $this->hasOne(TechnicianProfile::class);
    }

    public function assignedServiceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'technician_id');
    }

    public function createdServiceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'created_by');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(Route::class, 'technician_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isTechnician(): bool
    {
        return $this->hasRole('technician');
    }

    public function isOperator(): bool
    {
        return $this->hasRole('operator');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function getInitialsAttribute(): string
    {
        $parts = explode(' ', $this->name);

        return mb_strtoupper(
            mb_substr($parts[0], 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '')
        );
    }
}
