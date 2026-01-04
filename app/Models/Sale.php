<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'sale_number',
        'offline_id',
        'cashier_session_id',
        'customer_id',
        'user_id',
        'payment_method_id',
        'subtotal',
        'discount',
        'total',
        'status',
        'metadata',
    ];

    protected $casts = [
        'subtotal' => 'decimal:4',
        'discount' => 'decimal:4',
        'total' => 'decimal:4',
        'metadata' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
