<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    use BelongsToTenant;

    /** Estados y su orden en el flujo de despacho. */
    public const FLOW = ['pendiente', 'confirmado', 'en_ruta', 'entregado'];

    public const LABELS = [
        'pendiente' => 'Pendiente',
        'confirmado' => 'Confirmado',
        'en_ruta' => 'En ruta',
        'entregado' => 'Entregado',
        'cancelado' => 'Cancelado',
    ];

    /** Estado siguiente en el flujo, o null si ya está entregado/cancelado. */
    public function nextStatus(): ?string
    {
        $i = array_search($this->status, self::FLOW, true);

        if ($i === false || $i === count(self::FLOW) - 1) {
            return null;
        }

        return self::FLOW[$i + 1];
    }

    protected $fillable = [
        'tenant_id', 'branch_id', 'customer_id', 'address_id',
        'delivery_zone_id', 'courier_id',
        'subtotal', 'delivery_fee', 'total',
        'status', 'channel',
        'scheduled_date', 'scheduled_slot', 'scheduled_time', 'notes',
        'stock_applied_at', 'containers_applied_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'scheduled_date' => 'date',
        'stock_applied_at' => 'datetime',
        'containers_applied_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
