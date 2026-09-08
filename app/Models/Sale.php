<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use BelongsToTenant;

    public const LABELS = [
        'cobrada' => 'Cobrada',
        'en_espera' => 'En espera',
        'cancelada' => 'Cancelada',
    ];

    protected $fillable = [
        'tenant_id', 'branch_id', 'order_id', 'customer_id', 'user_id',
        'payment_method_id', 'cash_session_id', 'status', 'subtotal',
        'discount_total', 'total', 'notes', 'paid_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
