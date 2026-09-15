<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\Tenant;
use App\Services\Push\PushDispatcher;

class OrderObserver
{
    public function __construct(private PushDispatcher $push) {}

    /**
     * Asigna el número del pedido (secuencial por negocio) antes de insertarlo,
     * respetando el número inicial configurado por el negocio.
     */
    public function creating(Order $order): void
    {
        if ($order->number !== null) {
            return;
        }

        $start = (int) (Tenant::find($order->tenant_id)?->order_number_start ?? 1);
        $last = (int) Order::withoutGlobalScopes()->where('tenant_id', $order->tenant_id)->max('number');

        $order->number = max($last + 1, $start);
    }

    public function created(Order $order): void
    {
        // Avisamos al dueño de pedidos reales (no de pruebas del playground).
        if (in_array($order->channel, ['whatsapp', 'manual'], true)) {
            $this->push->notifyNewOrder($order);
        }
    }
}
