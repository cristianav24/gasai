<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\Agent\AgentContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerNameFromWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
    }

    public function test_usa_el_nombre_de_perfil_de_whatsapp_cuando_no_hay_nombre(): void
    {
        // Número oculto (solo BSUID) pero con nombre de perfil de WhatsApp.
        $conv = Conversation::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'channel' => 'whatsapp', 'status' => 'bot',
            'wa_user_id' => 'PE.1309061394505794', 'contact_name' => 'Javier Yañac Vallejos',
        ]);
        $ctx = new AgentContext($this->tenant, $conv, null);

        $customer = $ctx->ensureCustomer(); // sin nombre explícito

        $this->assertSame('Javier Yañac Vallejos', $customer->name);
        $this->assertSame('Javier Yañac Vallejos', $customer->displayName());
        $this->assertStringNotContainsString('PE.', $customer->displayName());
    }

    public function test_displayname_nunca_muestra_el_bsuid(): void
    {
        $c = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'wa_user_id' => 'PE.999', // sin name/phone/username
        ]);

        $this->assertSame('Cliente', $c->displayName());
    }

    public function test_un_nombre_explicito_tiene_prioridad_sobre_el_perfil(): void
    {
        $conv = Conversation::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'channel' => 'whatsapp', 'status' => 'bot',
            'wa_user_id' => 'PE.ABC', 'contact_name' => 'Nombre de perfil',
        ]);
        $ctx = new AgentContext($this->tenant, $conv, null);

        $customer = $ctx->ensureCustomer('Cristian');

        $this->assertSame('Cristian', $customer->name);
    }
}
