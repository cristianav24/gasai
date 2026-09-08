<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentContext;
use App\Services\Agent\OrderPricing;

/**
 * Calcula el total de un pedido (subtotal + envío) con precios reales de la BD.
 * El LLM solo comunica el resultado; no calcula nada él mismo.
 */
class CalcularTotal implements Tool
{
    public function __construct(private OrderPricing $pricing) {}

    public function name(): string
    {
        return 'calcular_total';
    }

    public function description(): string
    {
        return 'Calcula el subtotal, el costo de envío y el total de un pedido usando los precios reales. '
            . 'Úsala siempre antes de confirmar un total; nunca sumes tú los precios.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'description' => 'Lista de productos y cantidades.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'producto_id' => ['type' => 'integer'],
                            'cantidad' => ['type' => 'integer'],
                        ],
                        'required' => ['producto_id', 'cantidad'],
                    ],
                ],
                'zona_id' => [
                    'type' => 'integer',
                    'description' => 'ID de la zona de entrega para incluir el costo de envío (opcional).',
                ],
            ],
            'required' => ['items'],
        ];
    }

    public function handle(array $arguments, AgentContext $context): array
    {
        $items = $arguments['items'] ?? [];
        $zonaId = isset($arguments['zona_id']) ? (int) $arguments['zona_id'] : null;

        return $this->pricing->calcular($items, $zonaId);
    }
}
