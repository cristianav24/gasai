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

    /** @return array<int, array<string, mixed>> */
    private function line(int $qty = 1, float $charged = 25.0): array
    {
        return [[
            'product_id' => $this->producto->id,
            'name' => 'Bidón 20L',
            'unit' => 'bidón',
            'list' => 25.0,
            'charged' => $charged,
            'qty' => $qty,
        ]];
    }

    public function test_agregar_un_producto_lo_pone_en_el_ticket(): void
    {
        Livewire::test(PuntoDeVenta::class)
            ->call('addProduct', $this->producto->id)
            ->assertCount('cart', 1)
            ->call('addProduct', $this->producto->id)  // el mismo suma cantidad
            ->assertCount('cart', 1)
            ->assertSet('cart.0.qty', 2);
    }

    public function test_cobrar_con_precio_editado_calcula_margen(): void
    {
        Livewire::test(PuntoDeVenta::class)
            ->set('cart', $this->line(qty: 2, charged: 22.0)) // descuento a mano
            ->set('paymentMethodId', $this->efectivo->id)
            ->call('cobrar');

        $sale = Sale::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('cobrada', $sale->status);
        $this->assertSame('50.00', $sale->subtotal);       // 2 x 25 lista
        $this->assertSame('44.00', $sale->total);          // 2 x 22 cobrado
        $this->assertSame('6.00', $sale->discount_total);
        $this->assertNotNull($sale->paid_at);
    }

    public function test_no_cobra_sin_metodo_de_pago(): void
    {
        Livewire::test(PuntoDeVenta::class)
            ->set('cart', $this->line())
            ->set('paymentMethodId', null)
            ->call('cobrar');

        $this->assertSame(0, Sale::withoutGlobalScopes()->count());
    }

    public function test_guardar_en_espera_sin_metodo(): void
    {
        Livewire::test(PuntoDeVenta::class)
            ->set('cart', $this->line())
            ->call('enEspera');

        $sale = Sale::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('en_espera', $sale->status);
        $this->assertNull($sale->paid_at);
    }

    public function test_cobrar_desde_pedido_preselecciona_cliente_e_items(): void
    {
        $customer = Customer::create(['phone' => '+51987654321', 'name' => 'Juan']);
        $order = Order::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
            'customer_id' => $customer->id, 'status' => 'en_ruta', 'channel' => 'whatsapp', 'total' => 25,
        ]);
        $order->items()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $this->producto->id,
            'product_name' => 'Bidón 20L', 'quantity' => 2, 'unit_price_list' => 25,
        ]);

        Livewire::withQueryParams(['order' => $order->id])
            ->test(PuntoDeVenta::class)
            ->assertSet('customerId', $customer->id)
            ->assertSet('orderId', $order->id)
            ->assertCount('cart', 1)
            ->assertSet('cart.0.qty', 2);
    }

    public function test_alta_rapida_de_cliente(): void
    {
        Livewire::test(PuntoDeVenta::class)
            ->set('newName', 'Pedro')
            ->set('newPhone', '+51900111222')
            ->call('saveCustomer');

        $customer = Customer::withoutGlobalScopes()->firstWhere('phone', '+51900111222');
        $this->assertNotNull($customer);
        $this->assertSame('Pedro', $customer->name);
    }

    public function test_venta_no_visible_para_otro_tenant(): void
    {
        Livewire::test(PuntoDeVenta::class)
            ->set('cart', $this->line())
            ->set('paymentMethodId', $this->efectivo->id)
            ->call('cobrar');

        $otro = Tenant::create(['name' => 'Otro', 'slug' => 'otro', 'rubro' => 'gas']);
        Filament::setTenant($otro, isQuiet: true);

        $this->assertSame(0, Sale::count());
        $this->assertSame(1, Sale::withoutGlobalScopes()->count());
    }
}
