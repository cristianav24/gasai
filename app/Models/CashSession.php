<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'user_id', 'status',
        'opening_amount', 'closing_amount', 'expected_amount', 'difference',
        'opened_at', 'closed_at', 'notes',
    ];

    protected $casts = [
        'opening_amount' => 'decimal:2',
        'closing_amount' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'difference' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function movementsTotal(): float
    {
        $entradas = (float) $this->movements()->where('type', 'entrada')->sum('amount');
        $salidas = (float) $this->movements()->where('type', 'salida')->sum('amount');

        return round($entradas - $salidas, 2);
    }

    /** Efectivo de ventas cobradas en este turno. */
    public function cashSalesTotal(): float
    {
        return round((float) $this->sales()->where('status', 'cobrada')->sum('total'), 2);
    }

    /** Efectivo esperado en caja: fondo + movimientos + ventas en efectivo. */
    public function expectedCash(): float
    {
        return round((float) $this->opening_amount + $this->movementsTotal() + $this->cashSalesTotal(), 2);
    }
}
