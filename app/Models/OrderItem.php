<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'order_id', 'product_id', 'product_name',
        'quantity', 'unit_price_list', 'unit_price_charged',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_list' => 'decimal:2',
        'unit_price_charged' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
