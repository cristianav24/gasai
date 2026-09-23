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

    /**
     * Con reparto por distancia el precio depende de la dirección (envío interno
     * prorrateado). El catálogo NO debe mostrar el precio base para que el bot no
     * cotice S/10 y luego termine en S/11: solo cotiza tras guardar la dirección.
     */
    public function test_con_reparto_por_distancia_el_catalogo_no_muestra_precio_base(): void
    {
        $tenant = Tenant::create([
            'name' => 'H2O Wanka', 'slug' => 'h2o', 'rubro' => 'agua',
            'delivery_center_lat' => -12.065, 'delivery_center_lng' => -75.205,
            'delivery_bands' => [['hasta_km' => 3, 'costo' => 0], ['hasta_km' => 6, 'costo' => 2]],
        ]);
        BotConfig::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'agent_name' => 'Manu', 'tone' => 'amable']);
        Product::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Recarga 20L', 'price' => 10, 'unit' => 'bidón', 'type' => 'recarga', 'active' => true]);

        $conv = Conversation::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'channel' => 'whatsapp', 'status' => 'bot']);
        $prompt = app(SystemPromptBuilder::class)->build(new AgentContext($tenant, $conv, null));

        $this->assertStringContainsString('Recarga 20L', $prompt);   // el producto sí aparece
        $this->assertStringNotContainsString('S/ 10.00', $prompt);   // pero sin su precio base
        $this->assertStringContainsString('NUNCA', $prompt);          // y con la regla anti-cotización
    }
}
