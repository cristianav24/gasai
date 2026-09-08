<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'sale_id', 'product_id', 'product_name',
        'quantity', 'unit_price_list', 'unit_price_charged', 'note',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_list' => 'decimal:2',
        'unit_price_charged' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Margen de la línea: (cobrado - lista) × cantidad. */
    public function marginTotal(): float
    {
        return round(((float) $this->unit_price_charged - (float) $this->unit_price_list) * $this->quantity, 2);
    }
}
