<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Agent\AgentContext;
use App\Services\Agent\OrderPricing;
use App\Services\Agent\Tools\BuscarCliente;
use App\Services\Agent\Tools\CrearPedido;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 3: las herramientas del agente respetan las reglas duras del proyecto.
 */
class AgentToolsTest extends TestCase
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

    public function test_calcular_total_usa_los_precios_de_la_base_de_datos(): void
    {
        $bidon = Product::create(['name' => 'Bidón 20L', 'price' => 12.50, 'unit' => 'bidón']);
        $zona = DeliveryZone::create(['name' => 'Centro', 'delivery_fee' => 3]);

        $calc = app(OrderPricing::class)->calcular(
            [['producto_id' => $bidon->id, 'cantidad' => 2]],
            $zona->id,
        );

        // 2 x 12.50 = 25.00 + 3 de envío = 28.00. El LLM no interviene.
        $this->assertSame(25.0, $calc['subtotal']);
        $this->assertSame(3.0, $calc['costo_envio']);
        $this->assertSame(28.0, $calc['total']);
        $this->assertEmpty($calc['errores']);
    }

    public function test_crear_pedido_rechaza_sin_fecha_ni_franja(): void
    {
        $bidon = Product::create(['name' => 'Bidón 20L', 'price' => 12.50, 'unit' => 'bidón']);
        $tool = app(CrearPedido::class);

        $res = $tool->handle([
            'cliente_id' => 1,
            'items' => [['producto_id' => $bidon->id, 'cantidad' => 1]],
            // sin fecha_programada ni franja
        ], $this->context());

        $this->assertFalse($res['ok']);
        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_crear_pedido_exige_hora_si_la_franja_es_hora_exacta(): void
    {
        $bidon = Product::create(['name' => 'Bidón 20L', 'price' => 12.50, 'unit' => 'bidón']);
        $tool = app(CrearPedido::class);

        $res = $tool->handle([
            'cliente_id' => 1,
            'items' => [['producto_id' => $bidon->id, 'cantidad' => 1]],
            'fecha_programada' => '2026-09-10',
            'franja' => 'hora_exacta',
            // sin hora
        ], $this->context());

        $this->assertFalse($res['ok']);
        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_crear_pedido_congela_precios_y_asigna_tenant(): void
    {
        $bidon = Product::create(['name' => 'Bidón 20L', 'price' => 12.50, 'unit' => 'bidón']);
        $customer = Customer::create(['phone' => '+51987654321', 'name' => 'Juan']);
        $tool = app(CrearPedido::class);

        $res = $tool->handle([
            'cliente_id' => $customer->id,
            'items' => [['producto_id' => $bidon->id, 'cantidad' => 2]],
            'fecha_programada' => '2026-09-10',
            'franja' => 'manana',
        ], $this->context($customer));

        $this->assertTrue($res['ok']);

        $order = Order::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($this->tenant->id, $order->tenant_id);
        $this->assertSame('pendiente', $order->status);
        $this->assertSame('25.00', $order->total);

        $item = $order->items()->withoutGlobalScopes()->first();
        $this->assertSame('Bidón 20L', $item->product_name);       // nombre congelado
        $this->assertSame('12.50', $item->unit_price_list);        // precio congelado
    }

    public function test_precio_congelado_no_cambia_si_el_producto_sube_de_precio(): void
    {
        $bidon = Product::create(['name' => 'Bidón 20L', 'price' => 12.50, 'unit' => 'bidón']);
        $customer = Customer::create(['phone' => '+51987654321', 'name' => 'Juan']);

        app(CrearPedido::class)->handle([
            'cliente_id' => $customer->id,
            'items' => [['producto_id' => $bidon->id, 'cantidad' => 1]],
            'fecha_programada' => '2026-09-10',
            'franja' => 'tarde',
        ], $this->context($customer));

        // El dueño sube el precio después de crear el pedido.
        $bidon->update(['price' => 20]);

        $item = Order::withoutGlobalScopes()->firstOrFail()->items()->withoutGlobalScopes()->first();
        $this->assertSame('12.50', $item->unit_price_list, 'El precio del pedido no debe cambiar retroactivamente');
    }

    public function test_buscar_cliente_no_encuentra_clientes_de_otro_tenant(): void
    {
        $otro = Tenant::create(['name' => 'Otro', 'slug' => 'otro', 'rubro' => 'gas']);
        Customer::withoutGlobalScopes()->create([
            'tenant_id' => $otro->id, 'phone' => '+51900000000', 'name' => 'Ajeno',
        ]);

        // Contexto del tenant H2O buscando el teléfono del cliente del otro tenant.
        $res = app(BuscarCliente::class)->handle(['telefono' => '+51900000000'], $this->context());

        $this->assertFalse($res['encontrado']);
    }
}
