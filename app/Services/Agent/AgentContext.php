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

    /**
     * Devuelve el cliente de ESTA conversación, creándolo si aún no existe y
     * atándolo a la identidad de WhatsApp de la conversación (wa_user_id/phone).
     * Regla dura: el cliente sobre el que actúan las herramientas SIEMPRE es el
     * de la conversación, nunca un id/teléfono que elija el modelo.
     */
    public function ensureCustomer(?string $name = null): Customer
    {
        if ($this->customer) {
            if ($name !== null && trim($name) !== '' && blank($this->customer->name)) {
                $this->customer->forceFill(['name' => trim($name)])->save();
            }

            return $this->customer;
        }

        $wa = $this->conversation->wa_user_id;
        $phone = $this->conversation->phone;
        $clean = ($name !== null && trim($name) !== '') ? trim($name) : null;

        // Reusar el cliente de esta identidad si ya existe (evita duplicados).
        $base = Customer::withoutGlobalScopes()->where('tenant_id', $this->tenantId());
        $customer = $wa ? (clone $base)->where('wa_user_id', $wa)->first() : null;
        if (! $customer && $phone) {
            $customer = (clone $base)->where('phone', $phone)->first();
        }

        if ($customer) {
            if ($clean !== null && blank($customer->name)) {
                $customer->forceFill(['name' => $clean])->save();
            }
        } else {
            $customer = Customer::withoutGlobalScopes()->create([
                'tenant_id' => $this->tenantId(),
                'wa_user_id' => $wa ?: null,
                'phone' => $phone ?: null,
                'name' => $clean,
            ]);
        }

        $this->conversation->forceFill(['customer_id' => $customer->id])->save();
        $this->customer = $customer;

        return $customer;
    }
}
