<?php

namespace Tests\Feature;

use App\Filament\Pages\Despacho;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Stock\StockService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Branch $branch;
    private Product $producto;

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
    }

    private function stockAt(Branch $branch): int
    {
        return (int) (StockLevel::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->where('product_id', $this->producto->id)
            ->value('quantity') ?? 0);
    }

    private function orderWithItems(int $qty): Order
    {
        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'status' => 'en_ruta',
            'channel' => 'whatsapp',
            'total' => 25 * $qty,
        ]);
        $order->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->producto->id,
            'product_name' => 'Bidón 20L',
            'quantity' => $qty,
            'unit_price_list' => 25,
        ]);

        return $order;
    }

    public function test_ajuste_manual_carga_stock(): void
    {
        app(StockService::class)->adjust($this->tenant->id, $this->branch->id, $this->producto->id, 10, 'ajuste', 'Carga inicial');

        $this->assertSame(10, $this->stockAt($this->branch));
    }

    public function test_entregar_descuenta_el_stock(): void
    {
        app(StockService::class)->adjust($this->tenant->id, $this->branch->id, $this->producto->id, 10, 'ajuste');
        $order = $this->orderWithItems(3);

        // Avanzar en el tablero: en_ruta → entregado descuenta.
        Livewire::test(Despacho::class)->call('advance', $order->id);

        $this->assertSame('entregado', $order->fresh()->status);
        $this->assertSame(7, $this->stockAt($this->branch)); // 10 - 3
        $this->assertNotNull($order->fresh()->stock_applied_at);
    }

    public function test_el_stock_no_se_descuenta_al_anotar_solo_al_entregar(): void
    {
        app(StockService::class)->adjust($this->tenant->id, $this->branch->id, $this->producto->id, 10, 'ajuste');
        $this->orderWithItems(3); // creado, no entregado

        $this->assertSame(10, $this->stockAt($this->branch)); // sin cambios
    }

    public function test_el_descuento_por_entrega_es_idempotente(): void
    {
        app(StockService::class)->adjust($this->tenant->id, $this->branch->id, $this->producto->id, 10, 'ajuste');
        $order = $this->orderWithItems(3);

        $service = app(StockService::class);
        $service->applyOrderDelivery($order);
        $service->applyOrderDelivery($order->fresh()); // segundo intento no descuenta de nuevo

        $this->assertSame(7, $this->stockAt($this->branch));
    }

    public function test_transferencia_entre_sucursales(): void
    {
        $sucursal2 = $this->tenant->branches()->create(['name' => 'Norte', 'active' => true]);
        app(StockService::class)->adjust($this->tenant->id, $this->branch->id, $this->producto->id, 10, 'ajuste');

        app(StockService::class)->transfer($this->tenant->id, $this->branch->id, $sucursal2->id, $this->producto->id, 4);

        $this->assertSame(6, $this->stockAt($this->branch));
        $this->assertSame(4, $this->stockAt($sucursal2));
    }

    public function test_no_transfiere_sin_stock_suficiente(): void
    {
        $sucursal2 = $this->tenant->branches()->create(['name' => 'Norte', 'active' => true]);
        app(StockService::class)->adjust($this->tenant->id, $this->branch->id, $this->producto->id, 2, 'ajuste');

        $this->expectException(RuntimeException::class);
        app(StockService::class)->transfer($this->tenant->id, $this->branch->id, $sucursal2->id, $this->producto->id, 5);
    }
}
