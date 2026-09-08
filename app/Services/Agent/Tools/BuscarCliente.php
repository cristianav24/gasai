<?php

namespace App\Services\Agent\Tools;

use App\Models\Customer;
use App\Services\Agent\AgentContext;

/**
 * Reconoce al cliente por su teléfono. Si existe, lo deja identificado en el
 * contexto para el resto del turno.
 */
class BuscarCliente implements Tool
{
    public function name(): string
    {
        return 'buscar_cliente';
    }

    public function description(): string
    {
        return 'Busca un cliente por su número de teléfono para reconocerlo y recuperar sus datos. '
            . 'Úsala al inicio de la conversación.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'telefono' => [
                    'type' => 'string',
                    'description' => 'Teléfono del cliente en formato internacional (E.164), ej. +51987654321.',
                ],
            ],
            'required' => ['telefono'],
        ];
    }

    public function handle(array $arguments, AgentContext $context): array
    {
        $phone = trim((string) ($arguments['telefono'] ?? ''));

        if ($phone === '') {
            return ['encontrado' => false, 'error' => 'Falta el teléfono.'];
        }

        // El tenant sale del contexto del servidor, nunca del argumento.
        $customer = Customer::where('phone', $phone)->first();

        if (! $customer) {
            return ['encontrado' => false];
        }

        $context->setCustomer($customer);

        return [
            'encontrado' => true,
            'cliente_id' => $customer->id,
            'nombre' => $customer->name,
            'telefono' => $customer->phone,
        ];
    }
}
