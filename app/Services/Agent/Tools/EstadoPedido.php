<?php

namespace App\Services\Agent\Tools;

use App\Models\Order;
use App\Services\Agent\AgentContext;

/**
 * Consulta el estado de un pedido.
 */
class EstadoPedido implements Tool
{
    public function name(): string
    {
        return 'estado_pedido';
    }

    public function description(): string
    {
        return 'Consulta el estado actual de un pedido por su ID.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'pedido_id' => ['type' => 'integer'],
            ],
            'required' => ['pedido_id'],
        ];
    }

    public function handle(array $arguments, AgentContext $context): array
    {
        // Solo puede consultar pedidos del cliente de ESTA conversación.
        $customer = $context->customer;

        $order = $customer
            ? Order::where('customer_id', $customer->id)->find($arguments['pedido_id'] ?? 0)
            : null;

        if (! $order) {
            return ['encontrado' => false];
        }

        return [
            'encontrado' => true,
            'pedido_id' => $order->id,
            'estado' => $order->status,
            'total' => (float) $order->total,
            'fecha_programada' => $order->scheduled_date?->toDateString(),
            'franja' => $order->scheduled_slot,
        ];
    }
}
