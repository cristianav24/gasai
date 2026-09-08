<?php

namespace Tests\Feature;

use App\Filament\Pages\PuntoDeVenta;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PuntoDeVentaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Branch $branch;
    private Product $producto;
    private PaymentMethod $efectivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $this->branch = $this->tenant->branches()->create(['name' => 'Central', 'active' => true]);

        $owner = User::factory()->create();
        $this->tenant->users()->attach($owner->id, ['role' => 'owner']);
        $this->actingAs($owner);
        Filament::setTenant($this->tenant, isQuiet: true);

        $this->producto = Product::create(['name' => 'Bidón 20L', 'price' => 25, 'unit' => 'bidón']);
        $this->efectivo = PaymentMethod::create(['name' => 'Efectivo']);
    }

    public function test_cobrar_una_venta_de_mostrador_con_precio_editado(): void
    {
        Livewire::test(PuntoDeVenta::class)
            ->set('data.items', [[
                'product_id' => $this->producto->id,
                'product_name' => 'Bidón 20L',
                'quantity' => 2,
                'unit_price_list' => 25.0,
                'unit_price_charged' => 22.0, // descuento aplicado a mano
                'note' => 'Cliente frecuente',
            ]])
            ->set('data.payment_method_id', $this->efectivo->id)
            ->call('cobrar');

        $sale = Sale::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('cobrada', $sale->status);
        $this->assertSame('50.00', $sale->subtotal);        // 2 x 25 (lista)
        $this->assertSame('44.00', $sale->total);           // 2 x 22 (cobrado)
        $this->assertSame('6.00', $sale->discount_total);   // 50 - 44
        $this->assertNotNull($sale->paid_at);

        $item = $sale->items()->withoutGlobalScopes()->first();
        $this->assertSame('25.00', $item->unit_price_list);
        $this->assertSame('22.00', $item->unit_price_charged);
    }

    public function test_no_cobra_sin_metodo_de_pago(): void
    {
        Livewire::test(PuntoDeVenta::class)
            ->set('data.items', [[
                'product_id' => $this->producto->id,
                'product_name' => 'Bidón 20L',
                'quantity' => 1,
                'unit_price_list' => 25.0,
                'unit_price_charged' => 25.0,
            ]])
            ->set('data.payment_method_id', null)
            ->call('cobrar');

        $this->assertSame(0, Sale::withoutGlobalScopes()->count());
    }

    public function test_guardar_en_espera_sin_metodo_de_pago(): void
    {
        Livewire::test(PuntoDeVenta::class)
            ->set('data.items', [[
                'product_id' => $this->producto->id,
                'product_name' => 'Bidón 20L',
                'quantity' => 1,
                'unit_price_list' => 25.0,
                'unit_price_charged' => 25.0,
            ]])
            ->call('guardarEnEspera');

        $sale = Sale::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('en_espera', $sale->status);
        $this->assertNull($sale->paid_at);
    }

    public function test_cobrar_desde_un_pedido_preselecciona_al_cliente(): void
    {
        $customer = Customer::create(['phone' => '+51987654321', 'name' => 'Juan']);
        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'status' => 'en_ruta',
            'channel' => 'whatsapp',
            'total' => 25,
        ]);
        $order->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->producto->id,
            'product_name' => 'Bidón 20L',
            'quantity' => 1,
            'unit_price_list' => 25,
        ]);

        // Simulamos la entrada desde el pedido (?order=id).
        $this->get(PuntoDeVenta::getUrl() . '?order=' . $order->id);

        Livewire::withQueryParams(['order' => $order->id])
            ->test(PuntoDeVenta::class)
            ->assertSet('data.customer_id', $customer->id)
            ->assertSet('orderId', $order->id);
    }

    public function test_cobrar_en_efectivo_se_vincula_a_la_caja_abierta(): void
    {
        $this->efectivo->update(['is_cash' => true]);
        $session = \App\Models\CashSession::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'status' => 'abierta',
            'opening_amount' => 100,
            'opened_at' => now(),
        ]);

        Livewire::test(PuntoDeVenta::class)
            ->set('data.items', [[
                'product_id' => $this->producto->id,
                'product_name' => 'Bidón 20L',
                'quantity' => 1,
                'unit_price_list' => 25.0,
                'unit_price_charged' => 25.0,
            ]])
            ->set('data.payment_method_id', $this->efectivo->id)
            ->call('cobrar');

        $sale = Sale::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($session->id, $sale->cash_session_id);
        $this->assertSame(125.0, $session->fresh()->expectedCash()); // 100 + 25
    }

    public function test_una_venta_no_es_visible_para_otro_tenant(): void
    {
        Livewire::test(PuntoDeVenta::class)
            ->set('data.items', [[
                'product_id' => $this->producto->id,
                'product_name' => 'Bidón 20L',
                'quantity' => 1,
                'unit_price_list' => 25.0,
                'unit_price_charged' => 25.0,
            ]])
            ->set('data.payment_method_id', $this->efectivo->id)
            ->call('cobrar');

        // Otro tenant activo no debe ver la venta.
        $otro = Tenant::create(['name' => 'Otro', 'slug' => 'otro', 'rubro' => 'gas']);
        Filament::setTenant($otro, isQuiet: true);

        $this->assertSame(0, Sale::count());
        $this->assertSame(1, Sale::withoutGlobalScopes()->count());
    }
}
