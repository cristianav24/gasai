<?php

namespace App\Services\Agent\Tools;

use App\Models\Customer;
use App\Services\Agent\AgentContext;
use App\Services\Containers\ContainerService;

/**
 * Consulta cuántos envases nuestros (bidones/balones) tiene un cliente, para que
 * el agente pueda avisarle ("tienes 2 bidones nuestros").
 */
class SaldoEnvases implements Tool
{
    public function __construct(private ContainerService $containers) {}

    public function name(): string
    {
        return 'saldo_envases';
    }

    public function description(): string
    {
        return 'Devuelve el saldo de envases retornables (bidones o balones) que el cliente tiene en su poder. '
            . 'Úsala cuando el cliente pregunte cuántos envases nuestros tiene, o al coordinar una recarga.';
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
            return ['envases' => [], 'error' => 'Cliente no encontrado.'];
        }

        return ['envases' => $this->containers->balancesFor($customer->id)];
    }
}
