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
use App\Services\Agent\Tools\ListarDirecciones;
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

    public function test_buscar_cliente_devuelve_al_cliente_de_la_conversacion(): void
    {
        // Sin cliente en la conversación: no lo reconoce.
        $this->assertFalse(app(BuscarCliente::class)->handle([], $this->context())['encontrado']);

        // Con cliente identificado (por el servidor): lo reconoce.
        $ana = Customer::create(['phone' => '+51987654321', 'name' => 'Ana']);
        $res = app(BuscarCliente::class)->handle([], $this->context($ana));

        $this->assertTrue($res['encontrado']);
        $this->assertSame('Ana', $res['nombre']);
    }

    /**
     * Regresión: una conversación de un contacto no registrado (p.ej. número
     * oculto) NUNCA debe actuar sobre otro cliente, aunque el modelo intente
     * colar un cliente_id. El pedido y las direcciones son de ESTA conversación.
     */
    public function test_las_herramientas_no_actuan_sobre_otro_cliente(): void
    {
        // Cristian, ya registrado, con su dirección.
        $cristian = Customer::create(['phone' => '+51941649964', 'name' => 'Cristian', 'wa_user_id' => 'PE.CRIS']);
        $cristian->addresses()->create(['tenant_id' => $this->tenant->id, 'address' => 'Jr Gonzales Prada 753']);

        // Conversación de Javier: número oculto, sin cliente identificado.
        $conv = Conversation::create([
            'tenant_id' => $this->tenant->id, 'channel' => 'whatsapp', 'status' => 'bot',
            'wa_user_id' => 'PE.JAVIER', 'contact_name' => 'Javier',
        ]);
        $context = new AgentContext($this->tenant, $conv, null);

        // listar_direcciones NO devuelve la dirección de Cristian.
        $dirs = app(ListarDirecciones::class)->handle([], $context);
        $this->assertSame([], $dirs['direcciones']);

        // crear_pedido ignora el cliente_id de Cristian y crea un cliente atado a Javier.
        $bidon = Product::create(['name' => 'Bidón 20L', 'price' => 25, 'unit' => 'bidón']);
        $res = app(CrearPedido::class)->handle([
            'cliente_id' => $cristian->id, // intento del modelo (debe ignorarse)
            'items' => [['producto_id' => $bidon->id, 'cantidad' => 1]],
            'fecha_programada' => '2026-09-12', 'franja' => 'manana',
        ], $context);

        $this->assertTrue($res['ok']);
        $order = Order::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertNotSame($cristian->id, $order->customer_id);

        $nuevo = Customer::withoutGlobalScopes()->find($order->customer_id);
        $this->assertSame('PE.JAVIER', $nuevo->wa_user_id);
        $this->assertSame($conv->fresh()->customer_id, $order->customer_id);
    }
}
