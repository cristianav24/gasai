<?php

namespace App\Services\Containers;

use App\Models\ContainerStock;
use App\Models\ContainerStockMovement;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Inventario físico de envases del negocio: llenos, vacíos y nuevos, por tipo.
 *
 * Efecto automático al entregar:
 * - Recarga (producto tipo 'recarga'): −1 lleno, +1 vacío (el cliente deja su vacío).
 * - Bidón nuevo (producto tipo 'venta'): −1 nuevo.
 * Los conteos nunca bajan de 0; el historial guarda el cambio real aplicado.
 */
class ContainerStockService
{
    /**
     * Aplica deltas a los contadores de un tipo de envase y registra el movimiento.
     *
     * @param  array{full?: int, empty?: int, new?: int}  $deltas
     */
    public function move(int $tenantId, int $containerTypeId, array $deltas, string $reason, ?int $orderId = null, ?string $note = null): ContainerStock
    {
        return DB::transaction(function () use ($tenantId, $containerTypeId, $deltas, $reason, $orderId, $note): ContainerStock {
            $stock = ContainerStock::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenantId, 'container_type_id' => $containerTypeId],
                ['full_count' => 0, 'empty_count' => 0, 'new_count' => 0],
            );

            $applied = [];
            foreach (['full_count' => 'full', 'empty_count' => 'empty', 'new_count' => 'new'] as $col => $key) {
                $d = (int) ($deltas[$key] ?? 0);
                $newVal = max(0, $stock->{$col} + $d);
                $applied[$key] = $newVal - $stock->{$col}; // cambio real tras el tope en 0
                $stock->{$col} = $newVal;
            }
            $stock->save();

            if ($applied['full'] !== 0 || $applied['empty'] !== 0 || $applied['new'] !== 0) {
                ContainerStockMovement::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'container_type_id' => $containerTypeId,
                    'order_id' => $orderId,
                    'user_id' => auth()->id(),
                    'reason' => $reason,
                    'full_delta' => $applied['full'],
                    'empty_delta' => $applied['empty'],
                    'new_delta' => $applied['new'],
                    'note' => $note,
                ]);
            }

            return $stock->refresh();
        });
    }

    /** Ingresa llenos, vacíos o nuevos (cantidad positiva). */
    public function add(int $tenantId, int $containerTypeId, string $bucket, int $qty, ?string $note = null): ContainerStock
    {
        return $this->move($tenantId, $containerTypeId, [$bucket => abs($qty)], 'ingreso', null, $note);
    }

    /** Retira llenos, vacíos o nuevos (cantidad positiva a descontar). */
    public function remove(int $tenantId, int $containerTypeId, string $bucket, int $qty, ?string $note = null): ContainerStock
    {
        return $this->move($tenantId, $containerTypeId, [$bucket => -abs($qty)], 'retiro', null, $note);
    }

    /** Recarga vacíos: pasan de vacío a lleno. */
    public function fill(int $tenantId, int $containerTypeId, int $qty, ?string $note = null): ContainerStock
    {
        $qty = abs($qty);

        return $this->move($tenantId, $containerTypeId, ['empty' => -$qty, 'full' => $qty], 'llenar', null, $note);
    }

    /** Efecto de la entrega de un pedido sobre el inventario de envases. Idempotente. */
    public function applyOrderDelivery(Order $order): void
    {
        if ($order->container_stock_applied_at !== null) {
            return;
        }

        $order->loadMissing('items.product');

        DB::transaction(function () use ($order): void {
            foreach ($order->items as $item) {
                $product = $item->product;
                if (! $product || ! $product->container_type_id) {
                    continue;
                }

                $qty = (int) $item->quantity;
                if ($product->type === 'recarga') {
                    // Damos un lleno y nos queda su vacío (cambio).
                    $this->move($order->tenant_id, $product->container_type_id, ['full' => -$qty, 'empty' => $qty], 'entrega_recarga', $order->id);
                } elseif ($product->type === 'venta') {
                    // Vendimos bidones nuevos.
                    $this->move($order->tenant_id, $product->container_type_id, ['new' => -$qty], 'entrega_nueva', $order->id);
                }
            }

            $order->forceFill(['container_stock_applied_at' => now()])->save();
        });
    }

    /** Inventario actual por tipo de envase (con su nombre). */
    public function stockFor(int $tenantId): Collection
    {
        return ContainerStock::withoutGlobalScopes()
            ->with('containerType')
            ->where('tenant_id', $tenantId)
            ->get();
    }
}
