<?php

namespace Tests\Feature;

use App\Filament\Pages\Despacho;
use App\Models\Branch;
use App\Models\ContainerBalance;
use App\Models\ContainerType;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Agent\AgentContext;
use App\Services\Agent\Tools\SaldoEnvases;
use App\Services\Containers\ContainerService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContainersTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Branch $branch;
    private ContainerType $bidon;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $this->branch = $this->tenant->branches()->create(['name' => 'Central', 'active' => true]);

        $owner = User::factory()->create();
        $this->tenant->users()->attach($owner->id, ['role' => 'owner']);
        $this->actingAs($owner);
        Filament::setTenant($this->tenant, isQuiet: true);

        $this->bidon = ContainerType::create(['name' => 'Bidón 20L']);
        $this->customer = Customer::create(['phone' => '+51987654321', 'name' => 'Juan']);
    }

    private function product(string $type): Product
    {
        return Product::create([
            'name' => $type === 'venta' ? 'Bidón 20L nuevo' : 'Recarga 20L',
            'price' => $type === 'venta' ? 25 : 8,
            'unit' => 'bidón',
            'type' => $type,
            'container_type_id' => $this->bidon->id,
        ]);
    }

    private function deliverOrder(Product $product, int $qty): Order
    {
        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'status' => 'en_ruta',
            'channel' => 'whatsapp',
            'total' => 25 * $qty,
        ]);
        $order->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $qty,
            'unit_price_list' => $product->price,
        ]);

        // Stock suficiente para que la entrega no quede bloqueada.
        app(\App\Services\Stock\StockService::class)
            ->adjust($this->tenant->id, $this->branch->id, $product->id, $qty, 'ajuste');

        Livewire::test(Despacho::class)->call('advance', $order->id); // → entregado

        return $order;
    }

    private function balance(): int
    {
        return (int) (ContainerBalance::withoutGlobalScopes()
            ->where('customer_id', $this->customer->id)
            ->where('container_type_id', $this->bidon->id)
            ->value('balance') ?? 0);
    }

    public function test_venta_de_envase_nuevo_suma_al_saldo_del_cliente(): void
    {
        $this->deliverOrder($this->product('venta'), 2);

        $this->assertSame(2, $this->balance());
    }

    public function test_recarga_no_cambia_el_saldo(): void
    {
        // El cliente ya tenía 2 envases nuestros.
        app(ContainerService::class)->adjust($this->tenant->id, $this->customer->id, $this->bidon->id, 2, 'ajuste');

        $this->deliverOrder($this->product('recarga'), 2);

        $this->assertSame(2, $this->balance()); // sin cambios
    }

    public function test_registrar_devolucion_baja_el_saldo(): void
    {
        app(ContainerService::class)->adjust($this->tenant->id, $this->customer->id, $this->bidon->id, 3, 'ajuste');

        app(ContainerService::class)->registerReturn($this->tenant->id, $this->customer->id, $this->bidon->id, 2);

        $this->assertSame(1, $this->balance());
    }

    public function test_el_efecto_de_envases_es_idempotente(): void
    {
        $order = $this->deliverOrder($this->product('venta'), 2);

        // Reintentar la entrega no vuelve a sumar.
        app(ContainerService::class)->applyOrderDelivery($order->fresh());

        $this->assertSame(2, $this->balance());
    }

    public function test_la_herramienta_saldo_envases_devuelve_el_saldo(): void
    {
        app(ContainerService::class)->adjust($this->tenant->id, $this->customer->id, $this->bidon->id, 2, 'ajuste');

        $conversation = Conversation::create([
            'tenant_id' => $this->tenant->id, 'channel' => 'playground', 'status' => 'bot',
        ]);
        $context = new AgentContext($this->tenant, $conversation, $this->customer);

        $res = app(SaldoEnvases::class)->handle(['cliente_id' => $this->customer->id], $context);

        $this->assertCount(1, $res['envases']);
        $this->assertSame('Bidón 20L', $res['envases'][0]['tipo']);
        $this->assertSame(2, $res['envases'][0]['saldo']);
    }

    public function test_la_pantalla_de_envases_renderiza_con_saldos(): void
    {
        app(ContainerService::class)->adjust($this->tenant->id, $this->customer->id, $this->bidon->id, 3, 'entrega_nueva');

        Livewire::test(\App\Filament\Pages\Envases::class)
            ->assertOk()
            ->assertSee('Juan')
            ->assertSee('Bidón 20L');
    }
}
