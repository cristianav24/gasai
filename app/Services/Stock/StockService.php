<?php

namespace App\Services\Stock;

use App\Models\Order;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Inventario por sucursal. El stock se descuenta AL ENTREGAR, no al anotar el
 * pedido (regla del proyecto). Toda operación deja un movimiento para trazabilidad.
 */
class StockService
{
    /**
     * Ajusta el stock de un producto en una sucursal por un delta (positivo o
     * negativo) y registra el movimiento.
     */
    public function adjust(int $tenantId, int $branchId, int $productId, int $delta, string $type, ?string $reference = null): StockLevel
    {
        return DB::transaction(function () use ($tenantId, $branchId, $productId, $delta, $type, $reference): StockLevel {
            $level = StockLevel::withoutGlobalScopes()->firstOrCreate(
                ['branch_id' => $branchId, 'product_id' => $productId],
                ['tenant_id' => $tenantId, 'quantity' => 0],
            );

            $level->increment('quantity', $delta);

            StockMovement::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'product_id' => $productId,
                'user_id' => auth()->id(),
                'type' => $type,
                'quantity' => $delta,
                'reference' => $reference,
            ]);

            return $level->refresh();
        });
    }

    /** Transfiere unidades de una sucursal a otra (requiere stock suficiente). */
    public function transfer(int $tenantId, int $fromBranchId, int $toBranchId, int $productId, int $quantity): void
    {
        if ($quantity < 1) {
            throw new RuntimeException('La cantidad a transferir debe ser mayor a cero.');
        }

        if ($fromBranchId === $toBranchId) {
            throw new RuntimeException('El origen y el destino deben ser distintos.');
        }

        $origen = StockLevel::withoutGlobalScopes()
            ->where('branch_id', $fromBranchId)->where('product_id', $productId)->first();

        if (! $origen || $origen->quantity < $quantity) {
            throw new RuntimeException('No hay stock suficiente en la sucursal de origen.');
        }

        DB::transaction(function () use ($tenantId, $fromBranchId, $toBranchId, $productId, $quantity): void {
            $ref = "transferencia sucursal {$fromBranchId}→{$toBranchId}";
            $this->adjust($tenantId, $fromBranchId, $productId, -$quantity, 'transferencia_salida', $ref);
            $this->adjust($tenantId, $toBranchId, $productId, $quantity, 'transferencia_entrada', $ref);
        });
    }

    /**
     * Descuenta el stock de un pedido al entregarlo. Idempotente: si ya se aplicó
     * (stock_applied_at), no vuelve a descontar.
     */
    public function applyOrderDelivery(Order $order): void
    {
        if ($order->stock_applied_at !== null) {
            return;
        }

        $order->loadMissing('items');

        DB::transaction(function () use ($order): void {
            foreach ($order->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $this->adjust(
                    tenantId: $order->tenant_id,
                    branchId: $order->branch_id,
                    productId: $item->product_id,
                    delta: -$item->quantity,
                    type: 'entrega',
                    reference: "pedido #{$order->id}",
                );
            }

            $order->forceFill(['stock_applied_at' => now()])->save();
        });
    }
}
