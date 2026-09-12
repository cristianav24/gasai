<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentContext;

/**
 * Registra el nombre del cliente que está escribiendo. El cliente se ata a la
 * identidad de WhatsApp de la conversación; el modelo NO elige a quién.
 */
class GuardarCliente implements Tool
{
    public function name(): string
    {
        return 'guardar_cliente';
    }

    public function description(): string
    {
        return 'Registra el nombre del cliente que te escribe (cuando es nuevo y te lo dio). '
            . 'No pidas ni envíes su número: el sistema ya sabe quién escribe.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'nombre' => ['type' => 'string', 'description' => 'Nombre del cliente que está escribiendo.'],
            ],
            'required' => ['nombre'],
        ];
    }

    public function handle(array $arguments, AgentContext $context): array
    {
        $name = trim((string) ($arguments['nombre'] ?? ''));

        if ($name === '') {
            return ['ok' => false, 'error' => 'Falta el nombre.'];
        }

        $customer = $context->ensureCustomer($name);

        // Si ya existía con otro nombre, lo actualizamos al que dio ahora.
        if ($customer->name !== $name) {
            $customer->forceFill(['name' => $name])->save();
        }

        return [
            'ok' => true,
            'nombre' => $customer->name,
            'telefono' => $customer->phone,
        ];
    }
}
