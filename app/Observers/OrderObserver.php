<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Push\PushDispatcher;

class OrderObserver
{
    public function __construct(private PushDispatcher $push) {}

    public function created(Order $order): void
    {
        // Avisamos al dueño de pedidos reales (no de pruebas del playground).
        if (in_array($order->channel, ['whatsapp', 'manual'], true)) {
            $this->push->notifyNewOrder($order);
        }
    }
}
