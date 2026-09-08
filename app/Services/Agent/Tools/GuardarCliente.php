<?php

namespace App\Services\Agent\Tools;

use App\Models\Customer;
use App\Services\Agent\AgentContext;

/**
 * Crea (o actualiza) un cliente por su teléfono y lo deja identificado.
 */
class GuardarCliente implements Tool
{
    public function name(): string
    {
        return 'guardar_cliente';
    }

    public function description(): string
    {
        return 'Registra un cliente nuevo con su nombre y teléfono, o actualiza el nombre si ya existe. '
            . 'Úsala cuando el cliente no estaba registrado y te dio sus datos.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'nombre' => ['type' => 'string', 'description' => 'Nombre del cliente.'],
                'telefono' => [
                    'type' => 'string',
                    'description' => 'Teléfono en formato internacional (E.164), ej. +51987654321.',
                ],
            ],
            'required' => ['nombre', 'telefono'],
        ];
    }

    public function handle(array $arguments, AgentContext $context): array
    {
        $phone = trim((string) ($arguments['telefono'] ?? ''));
        $name = trim((string) ($arguments['nombre'] ?? ''));

        if ($phone === '' || $name === '') {
            return ['ok' => false, 'error' => 'Faltan nombre o teléfono.'];
        }

        // tenant_id lo asigna el trait BelongsToTenant desde el contexto activo,
        // pero al correr fuera de Filament lo pasamos explícito por seguridad.
        $customer = Customer::updateOrCreate(
            ['tenant_id' => $context->tenantId(), 'phone' => $phone],
            ['name' => $name],
        );

        $context->setCustomer($customer);

        return [
            'ok' => true,
            'cliente_id' => $customer->id,
            'nombre' => $customer->name,
            'telefono' => $customer->phone,
        ];
    }
}
