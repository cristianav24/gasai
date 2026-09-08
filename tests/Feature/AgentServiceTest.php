<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Agent\AgentContext;
use App\Services\Agent\AgentService;
use App\Services\Llm\LlmProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeLlmProvider;
use Tests\TestCase;

/**
 * Fase 3: el loop del agente (con proveedor de LLM falso, sin red).
 */
class AgentServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $this->tenant->branches()->create(['name' => 'Central', 'active' => true]);
        Filament::setTenant($this->tenant, isQuiet: true);
    }

    private function context(?Customer $customer = null): AgentContext
    {
        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id,
            'channel' => 'playground',
            'status' => 'bot',
        ]);

        return new AgentContext($this->tenant, $conversation, $customer);
    }

    private function withFake(FakeLlmProvider $fake): AgentService
    {
        $this->app->instance(LlmProvider::class, $fake);

        return $this->app->make(AgentService::class);
    }

    public function test_el_agente_ejecuta_una_herramienta_y_luego_responde(): void
    {
        Product::create(['name' => 'Bidón 20L', 'price' => 12.50, 'unit' => 'bidón']);

        // El modelo primero llama listar_productos, luego responde con texto.
        $fake = new FakeLlmProvider(
            FakeLlmProvider::toolCall('listar_productos', []),
            FakeLlmProvider::text('Tenemos Bidón 20L a S/ 12.50. ¿Cuántos quieres?'),
        );

        $agent = $this->withFake($fake);
        $context = $this->context();

        $reply = $agent->handle($context, 'Hola, ¿qué venden?');

        $this->assertStringContainsString('Bidón 20L', $reply);

        // La herramienta quedó en la traza de debug.
        $trace = $agent->getLastToolTrace();
        $this->assertSame('listar_productos', $trace[0]['tool']);

        // Se persistieron el mensaje del cliente y la respuesta del agente.
        $roles = $context->conversation->messages()->orderBy('id')->pluck('role')->all();
        $this->assertSame(['user', 'assistant'], $roles);
    }

    public function test_el_agente_crea_un_pedido_a_traves_de_la_herramienta(): void
    {
        $bidon = Product::create(['name' => 'Bidón 20L', 'price' => 12.50, 'unit' => 'bidón']);
        $customer = Customer::create(['phone' => '+51987654321', 'name' => 'Juan']);

        $fake = new FakeLlmProvider(
            FakeLlmProvider::toolCall('crear_pedido', [
                'cliente_id' => $customer->id,
                'items' => [['producto_id' => $bidon->id, 'cantidad' => 3]],
                'fecha_programada' => '2026-09-10',
                'franja' => 'manana',
            ]),
            FakeLlmProvider::text('¡Listo! Tu pedido quedó agendado para mañana.'),
        );

        $agent = $this->withFake($fake);
        $reply = $agent->handle($this->context($customer), 'Quiero 3 bidones para mañana en la mañana');

        $this->assertStringContainsString('agendado', $reply);

        $order = Order::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('37.50', $order->total);       // 3 x 12.50, calculado por código
        $this->assertSame('manana', $order->scheduled_slot);
    }

    public function test_el_agente_escala_a_humano_al_pasarse_del_maximo_de_iteraciones(): void
    {
        // Cola vacía: el fake siempre pide una herramienta, forzando el tope.
        $fake = new FakeLlmProvider();

        $agent = $this->withFake($fake);
        $context = $this->context();

        $reply = $agent->handle($context, 'dame vueltas');

        $this->assertStringContainsString('persona del equipo', $reply);
        $this->assertSame('humano', $context->conversation->fresh()->status);
    }
}
