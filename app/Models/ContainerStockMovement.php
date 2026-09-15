<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un renglón del historial del inventario de envases: qué cambió y por qué.
 */
class ContainerStockMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'container_type_id', 'order_id', 'user_id',
        'reason', 'full_delta', 'empty_delta', 'new_delta', 'note',
    ];

    protected $casts = [
        'full_delta' => 'integer',
        'empty_delta' => 'integer',
        'new_delta' => 'integer',
    ];

    /** Etiquetas legibles del motivo. */
    public const REASONS = [
        'ingreso' => 'Ingreso',
        'retiro' => 'Retiro',
        'llenar' => 'Llenado de vacíos',
        'entrega_recarga' => 'Entrega (recarga)',
        'entrega_nueva' => 'Entrega (bidón nuevo)',
        'ajuste' => 'Ajuste',
    ];

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    public function containerType(): BelongsTo
    {
        return $this->belongsTo(ContainerType::class);
    }
}
