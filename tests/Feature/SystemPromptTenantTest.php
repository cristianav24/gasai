<?php

namespace Tests\Feature;

use App\Models\BotConfig;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Agent\AgentContext;
use App\Services\Agent\SystemPromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemPromptTenantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regresión: en el job de WhatsApp no hay tenant en el scope global, así que
     * el prompt debe acotar por el negocio de la conversación y NO leer la config
     * ni los productos de otro negocio.
     */
    public function test_el_prompt_usa_la_config_del_negocio_de_la_conversacion(): void
    {
        // Otro negocio, creado primero (sería el que devuelve ::first() sin filtro).
        $otro = Tenant::create(['name' => 'Otro', 'slug' => 'otro', 'rubro' => 'gas']);
        BotConfig::withoutGlobalScopes()->create(['tenant_id' => $otro->id, 'agent_name' => 'Asistente', 'tone' => 'amable']);
        Product::withoutGlobalScopes()->create(['tenant_id' => $otro->id, 'name' => 'Balón 10kg', 'price' => 40, 'unit' => 'unidad', 'type' => 'venta', 'active' => true]);

        // El negocio de la conversación.
        $mio = Tenant::create(['name' => 'H2O Wanka', 'slug' => 'h2o', 'rubro' => 'agua']);
        BotConfig::withoutGlobalScopes()->create(['tenant_id' => $mio->id, 'agent_name' => 'Manu', 'tone' => 'amable']);
        Product::withoutGlobalScopes()->create(['tenant_id' => $mio->id, 'name' => 'Bidón 20 litros', 'price' => 10, 'unit' => 'unidad', 'type' => 'recarga', 'active' => true]);

        $conv = Conversation::withoutGlobalScopes()->create(['tenant_id' => $mio->id, 'channel' => 'whatsapp', 'status' => 'bot']);
        $prompt = app(SystemPromptBuilder::class)->build(new AgentContext($mio, $conv, null));

        $this->assertStringContainsString('Manu', $prompt);
        $this->assertStringContainsString('Bidón 20 litros', $prompt);
        $this->assertStringNotContainsString('Balón 10kg', $prompt); // producto del otro negocio
    }
}
