<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentContext;

/**
 * Dice si ya conocemos al cliente que está escribiendo. La identidad sale del
 * servidor (la conversación), NUNCA de un teléfono que mande el modelo.
 */
class BuscarCliente implements Tool
{
    public function name(): string
    {
        return 'buscar_cliente';
    }

    public function description(): string
    {
        return 'Indica si ya conocemos al cliente que te está escribiendo (por su identidad de WhatsApp). '
            . 'No recibe datos: el sistema ya sabe quién escribe. Úsala al inicio para saber si es cliente nuevo o recurrente.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
        ];
    }

    public function handle(array $arguments, AgentContext $context): array
    {
        $customer = $context->customer;

        if (! $customer) {
            return ['encontrado' => false];
        }

        return [
            'encontrado' => true,
            'nombre' => $customer->name,
            'telefono' => $customer->phone,
        ];
    }
}
