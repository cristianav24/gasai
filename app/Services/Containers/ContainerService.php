<?php

namespace App\Services\Containers;

use App\Models\ContainerBalance;
use App\Models\ContainerMovement;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Saldo de envases retornables (bidones/balones) en poder de cada cliente.
 *
 * Reglas al entregar:
 * - Venta de envase nuevo: el cliente se queda con un envase nuestro → saldo +cantidad.
 * - Recarga: intercambia vacío por lleno → el saldo no cambia.
 * - Devolución: el cliente devuelve envases → saldo −cantidad.
 */
class ContainerService
{
    public function adjust(int $tenantId, int $customerId, int $containerTypeId, int $delta, string $type, ?string $reference = null): ContainerBalance
    {
        return DB::transaction(function () use ($tenantId, $customerId, $containerTypeId, $delta, $type, $reference): ContainerBalance {
            $balance = ContainerBalance::withoutGlobalScopes()->firstOrCreate(
                ['customer_id' => $customerId, 'container_type_id' => $containerTypeId],
                ['tenant_id' => $tenantId, 'balance' => 0],
            );

            $balance->increment('balance', $delta);

            ContainerMovement::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'container_type_id' => $containerTypeId,
                'user_id' => auth()->id(),
                'type' => $type,
                'delta' => $delta,
                'reference' => $reference,
            ]);

            return $balance->refresh();
        });
    }

    /** Registra la devolución de envases de un cliente. */
    public function registerReturn(int $tenantId, int $customerId, int $containerTypeId, int $quantity, ?string $reference = 'devolución'): ContainerBalance
    {
        return $this->adjust($tenantId, $customerId, $containerTypeId, -abs($quantity), 'devolucion', $reference);
    }

    /**
     * Aplica el efecto sobre envases al entregar un pedido. Idempotente.
     */
    public function applyOrderDelivery(Order $order): void
    {
        if ($order->containers_applied_at !== null) {
            return;
        }

        // Negocios que venden el envase (no lo prestan) no manejan saldo de envases;
        // sin cliente identificado tampoco hay a quién cargárselo.
        if (! $order->tenant?->tracks_containers || ! $order->customer_id) {
            $order->forceFill(['containers_applied_at' => now()])->save();

            return;
        }

        $order->loadMissing('items.product');

        DB::transaction(function () use ($order): void {
            foreach ($order->items as $item) {
                $product = $item->product;
                if (! $product || ! $product->container_type_id) {
                    continue;
                }

                // Solo la venta de envase nuevo suma; la recarga no cambia el saldo.
                if ($product->type === 'venta') {
                    $this->adjust(
                        tenantId: $order->tenant_id,
                        customerId: $order->customer_id,
                        containerTypeId: $product->container_type_id,
                        delta: $item->quantity,
                        type: 'entrega_nueva',
                        reference: "pedido #{$order->id}",
                    );
                }
            }

            $order->forceFill(['containers_applied_at' => now()])->save();
        });
    }

    /**
     * Saldo de envases de un cliente por tipo.
     *
     * @return array<int, array{tipo: string, saldo: int}>
     */
    public function balancesFor(int $customerId): array
    {
        return ContainerBalance::withoutGlobalScopes()
            ->with('containerType')
            ->where('customer_id', $customerId)
            ->where('balance', '!=', 0)
            ->get()
            ->map(fn (ContainerBalance $b): array => [
                'tipo' => $b->containerType?->name ?? 'Envase',
                'saldo' => $b->balance,
            ])
            ->all();
    }
}
