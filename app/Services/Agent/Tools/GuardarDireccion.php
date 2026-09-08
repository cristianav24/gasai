<?php

namespace App\Services\Agent\Tools;

use App\Models\Address;
use App\Models\Customer;
use App\Services\Agent\AgentContext;

/**
 * Guarda una dirección de entrega para un cliente.
 */
class GuardarDireccion implements Tool
{
    public function name(): string
    {
        return 'guardar_direccion';
    }

    public function description(): string
    {
        return 'Guarda una dirección de entrega para un cliente, con su referencia de ubicación.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cliente_id' => ['type' => 'integer', 'description' => 'ID del cliente.'],
                'direccion' => ['type' => 'string', 'description' => 'Dirección exacta.'],
                'referencia' => ['type' => 'string', 'description' => 'Referencia de ubicación (opcional).'],
                'principal' => ['type' => 'boolean', 'description' => 'Marcar como dirección principal.'],
            ],
            'required' => ['cliente_id', 'direccion'],
        ];
    }

    public function handle(array $arguments, AgentContext $context): array
    {
        $customer = Customer::find($arguments['cliente_id'] ?? 0);

        if (! $customer) {
            return ['ok' => false, 'error' => 'Cliente no encontrado.'];
        }

        $esPrincipal = (bool) ($arguments['principal'] ?? false);

        // Si esta será la principal, desmarcamos las demás del cliente.
        if ($esPrincipal) {
            $customer->addresses()->update(['is_primary' => false]);
        }

        $address = Address::create([
            'tenant_id' => $context->tenantId(),
            'customer_id' => $customer->id,
            'address' => trim((string) $arguments['direccion']),
            'reference' => isset($arguments['referencia']) ? trim((string) $arguments['referencia']) : null,
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
