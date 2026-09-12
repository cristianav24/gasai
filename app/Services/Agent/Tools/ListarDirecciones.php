<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentContext;

/**
 * Lista las direcciones guardadas del cliente de ESTA conversación.
 */
class ListarDirecciones implements Tool
{
    public function name(): string
    {
        return 'listar_direcciones';
    }

    public function description(): string
    {
        return 'Devuelve las direcciones guardadas del cliente que te escribe, para ofrecérselas '
            . 'en vez de pedírselas de nuevo. No recibe datos: usa al cliente de esta conversación.';
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
            return ['direcciones' => []];
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
