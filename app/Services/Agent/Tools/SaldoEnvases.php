<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentContext;
use App\Services\Containers\ContainerService;

/**
 * Saldo de envases retornables del cliente de ESTA conversación.
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
        return 'Devuelve cuántos envases retornables tiene el cliente que te escribe. '
            . 'No recibe cliente: siempre es el de esta conversación.';
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
            return ['envases' => []];
        }

        return ['envases' => $this->containers->balancesFor($customer->id)];
    }
}
