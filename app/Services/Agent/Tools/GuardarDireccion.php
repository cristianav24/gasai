<?php

namespace App\Services\Agent\Tools;

use App\Models\Address;
use App\Services\Agent\AgentContext;

/**
 * Guarda una dirección de entrega para el cliente de ESTA conversación.
 */
class GuardarDireccion implements Tool
{
    public function name(): string
    {
        return 'guardar_direccion';
    }

    public function description(): string
    {
        return 'Guarda una dirección de entrega del cliente que te escribe, con su referencia. '
            . 'No recibe cliente: siempre es el cliente de esta conversación.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'direccion' => ['type' => 'string', 'description' => 'Dirección exacta.'],
                'referencia' => ['type' => 'string', 'description' => 'Referencia de ubicación (opcional).'],
                'lat' => ['type' => 'number', 'description' => 'Latitud (de validar_direccion), si la tienes.'],
                'lng' => ['type' => 'number', 'description' => 'Longitud (de validar_direccion), si la tienes.'],
                'principal' => ['type' => 'boolean', 'description' => 'Marcar como dirección principal.'],
            ],
            'required' => ['direccion'],
        ];
    }

    public function handle(array $arguments, AgentContext $context): array
    {
        $direccion = trim((string) ($arguments['direccion'] ?? ''));
        if ($direccion === '') {
            return ['ok' => false, 'error' => 'Falta la dirección.'];
        }

        $customer = $context->ensureCustomer();

        $esPrincipal = (bool) ($arguments['principal'] ?? false);

        if ($esPrincipal) {
            $customer->addresses()->update(['is_primary' => false]);
        }

        $address = Address::create([
            'tenant_id' => $context->tenantId(),
            'customer_id' => $customer->id,
            'address' => $direccion,
            'reference' => isset($arguments['referencia']) ? trim((string) $arguments['referencia']) : null,
            'lat' => isset($arguments['lat']) ? (float) $arguments['lat'] : null,
            'lng' => isset($arguments['lng']) ? (float) $arguments['lng'] : null,
            'is_primary' => $esPrincipal,
        ]);

        return [
            'ok' => true,
            'direccion_id' => $address->id,
            'direccion' => $address->address,
            'referencia' => $address->reference,
            'principal' => $address->is_primary,
        ];
    }
}
