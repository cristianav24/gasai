<?php

namespace App\Services\Agent\Tools;

use App\Models\Customer;
use App\Services\Agent\AgentContext;

/**
 * Lista las direcciones guardadas de un cliente, para no volver a pedírselas.
 */
class ListarDirecciones implements Tool
{
    public function name(): string
    {
        return 'listar_direcciones';
    }

    public function description(): string
    {
        return 'Devuelve las direcciones guardadas de un cliente. Úsala para ofrecerle sus direcciones '
            . 'anteriores en vez de pedírselas de nuevo.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cliente_id' => ['type' => 'integer', 'description' => 'ID del cliente.'],
            ],
            'required' => ['cliente_id'],
        ];
    }

    public function handle(array $arguments, AgentContext $context): array
    {
        $customer = Customer::find($arguments['cliente_id'] ?? 0);

        if (! $customer) {
            return ['direcciones' => [], 'error' => 'Cliente no encontrado.'];
        }

        $direcciones = $customer->addresses()
            ->orderByDesc('is_primary')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a): array => [
                'direccion_id' => $a->id,
                'direccion' => $a->address,
                'referencia' => $a->reference,
                'principal' => $a->is_primary,
            ])
            ->all();

        return ['direcciones' => $direcciones];
    }
}
