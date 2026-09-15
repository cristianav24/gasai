<?php

namespace Tests\Feature;

use App\Filament\Pages\Inventario;
use App\Models\Branch;
use App\Models\ContainerStock;
use App\Models\ContainerType;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Containers\ContainerStockService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContainerStockTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Branch $branch;
    private ContainerType $tipo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua', 'tracks_containers' => true]);
        $this->branch = $this->tenant->branches()->create(['name' => 'Central', 'active' => true]);
        $this->tipo = ContainerType::create(['tenant_id' => $this->tenant->id, 'name' => 'Bidón 20L', 'active' => true]);

        $owner = User::factory()->create();
        $this->tenant->users()->attach($owner->id, ['role' => 'owner']);
        $this->actingAs($owner);
        Filament::setTenant($this->tenant, isQuiet: true);
    }

    private function service(): ContainerStockService
    {
        return app(ContainerStockService::class);
    }

    public function test_ingresar_retirar_y_llenar_ajustan_los_contadores(): void
    {
        $svc = $this->service();
        $svc->add($this->tenant->id, $this->tipo->id, 'full', 20);
        $svc->add($this->tenant->id, $this->tipo->id, 'empty', 15);
        $svc->add($this->tenant->id, $this->tipo->id, 'new', 8);

        $svc->remove($this->tenant->id, $this->tipo->id, 'full', 3);
        $svc->fill($this->tenant->id, $this->tipo->id, 5); // 5 vacíos -> llenos

        $stock = ContainerStock::withoutGlobalScopes()->where('container_type_id', $this->tipo->id)->first();
        $this->assertSame(22, $stock->full_count);  // 20 -3 +5
        $this->assertSame(10, $stock->empty_count); // 15 -5
        $this->assertSame(8, $stock->new_count);
    }

    public function test_los_contadores_nunca_bajan_de_cero(): void
    {
        $svc = $this->service();
        $svc->add($this->tenant->id, $this->tipo->id, 'full', 2);
        $svc->remove($this->tenant->id, $this->tipo->id, 'full', 10); // intenta retirar más

        $stock = ContainerStock::withoutGlobalScopes()->where('container_type_id', $this->tipo->id)->first();
        $this->assertSame(0, $stock->full_count);
    }

    public function test_entregar_recarga_descuenta_lleno_y_suma_vacio(): void
    {
        $svc = $this->service();
        $svc->add($this->tenant->id, $this->tipo->id, 'full', 10);

        $recarga = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bidón recarga', 'price' => 10,
            'unit' => 'unidad', 'type' => 'recarga', 'container_type_id' => $this->tipo->id, 'active' => true,
        ]);

        $order = Order::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
            'status' => 'pendiente', 'channel' => 'whatsapp', 'total' => 20,
        ]);
        $order->items()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $recarga->id,
            'product_name' => $recarga->name, 'quantity' => 2, 'unit_price_list' => 10,
        ]);

        $svc->applyOrderDelivery($order);

        $stock = ContainerStock::withoutGlobalScopes()->where('container_type_id', $this->tipo->id)->first();
        $this->assertSame(8, $stock->full_count);  // 10 - 2
        $this->assertSame(2, $stock->empty_count); // + 2 (cambio)

        // Idempotente: no vuelve a aplicar.
        $svc->applyOrderDelivery($order);
        $this->assertSame(8, $stock->fresh()->full_count);
    }

    public function test_entregar_bidon_nuevo_descuenta_de_nuevos(): void
    {
        $svc = $this->service();
        $svc->add($this->tenant->id, $this->tipo->id, 'new', 5);

        $nuevo = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Bidón nuevo', 'price' => 30,
            'unit' => 'unidad', 'type' => 'venta', 'container_type_id' => $this->tipo->id, 'active' => true,
        ]);

        $order = Order::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
            'status' => 'pendiente', 'channel' => 'whatsapp', 'total' => 30,
        ]);
        $order->items()->create([
            'tenant_id' => $this->tenant->id, 'product_id' => $nuevo->id,
            'product_name' => $nuevo->name, 'quantity' => 1, 'unit_price_list' => 30,
        ]);

        $svc->applyOrderDelivery($order);

        $stock = ContainerStock::withoutGlobalScopes()->where('container_type_id', $this->tipo->id)->first();
        $this->assertSame(4, $stock->new_count); // 5 - 1
    }

    public function test_la_pagina_de_inventario_muestra_los_bidones(): void
    {
        $this->service()->add($this->tenant->id, $this->tipo->id, 'full', 12);

        Livewire::test(Inventario::class)
            ->assertSee('Inventario de bidones')
            ->assertSee('Bidón 20L')
            ->assertSee('Llenos');
    }
}
