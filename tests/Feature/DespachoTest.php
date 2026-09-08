<?php

namespace Tests\Feature;

use App\Filament\Pages\Despacho;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DespachoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $this->branch = $this->tenant->branches()->create(['name' => 'Central', 'active' => true]);

        $owner = User::factory()->create();
        $this->tenant->users()->attach($owner->id, ['role' => 'owner']);
        $this->actingAs($owner);
        Filament::setTenant($this->tenant, isQuiet: true);
    }

    private function makeOrder(string $status = 'pendiente'): Order
    {
        return Order::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'status' => $status,
            'channel' => 'whatsapp',
            'total' => 25,
            'scheduled_date' => '2026-09-10',
            'scheduled_slot' => 'manana',
        ]);
    }

    public function test_avanzar_estado_sigue_el_flujo(): void
    {
        $order = $this->makeOrder('pendiente');

        Livewire::test(Despacho::class)->call('advance', $order->id);
        $this->assertSame('confirmado', $order->fresh()->status);

        Livewire::test(Despacho::class)->call('advance', $order->id);
        $this->assertSame('en_ruta', $order->fresh()->status);

        Livewire::test(Despacho::class)->call('advance', $order->id);
        $this->assertSame('entregado', $order->fresh()->status);

        // Ya entregado: no avanza más.
        Livewire::test(Despacho::class)->call('advance', $order->id);
        $this->assertSame('entregado', $order->fresh()->status);
    }

    public function test_asignar_repartidor(): void
    {
        $courier = User::factory()->create();
        $this->tenant->users()->attach($courier->id, ['role' => 'courier']);
        $order = $this->makeOrder();

        Livewire::test(Despacho::class)->call('assignCourier', $order->id, $courier->id);

        $this->assertSame($courier->id, $order->fresh()->courier_id);
    }

    public function test_cancelar_saca_el_pedido_del_tablero(): void
    {
        $order = $this->makeOrder('pendiente');

        $component = Livewire::test(Despacho::class);
        $component->call('cancel', $order->id);

        $this->assertSame('cancelado', $order->fresh()->status);

        // Los cancelados no aparecen en las columnas del tablero.
        $grupos = $component->instance()->ordersByStatus();
        $this->assertFalse($grupos->flatten()->contains(fn ($o) => $o->id === $order->id));
    }

    public function test_el_tablero_no_muestra_pedidos_de_otro_tenant(): void
    {
        $miPedido = $this->makeOrder('pendiente');

        $otro = Tenant::create(['name' => 'Otro', 'slug' => 'otro', 'rubro' => 'gas']);
        $otroBranch = $otro->branches()->create(['name' => 'Central', 'active' => true]);
        $ajeno = Order::withoutGlobalScopes()->create([
            'tenant_id' => $otro->id, 'branch_id' => $otroBranch->id,
            'status' => 'pendiente', 'channel' => 'whatsapp', 'total' => 10,
        ]);

        $grupos = Livewire::test(Despacho::class)->instance()->ordersByStatus();
        $ids = $grupos->flatten()->pluck('id');

        $this->assertTrue($ids->contains($miPedido->id));
        $this->assertFalse($ids->contains($ajeno->id));
    }
}
