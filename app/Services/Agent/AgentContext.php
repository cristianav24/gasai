<?php

namespace App\Services\Agent;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Tenant;

/**
 * Contexto del servidor para un turno del agente. El tenant y (si ya se
 * identificó) el cliente viven aquí. Las herramientas leen el tenant DE AQUÍ,
 * nunca de un argumento que mande el modelo. Regla dura del proyecto.
 */
class AgentContext
{
    public function __construct(
        public Tenant $tenant,
        public Conversation $conversation,
        public ?Customer $customer = null,
    ) {}

    public function tenantId(): int
    {
        return $this->tenant->getKey();
    }

    /** Un cliente quedó identificado en este turno (o antes). */
    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }
}
