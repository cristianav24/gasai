<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DeviceToken;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private function tenantConCourier(string $slug): array
    {
        $tenant = Tenant::create(['name' => $slug, 'slug' => $slug, 'rubro' => 'agua']);
        $branch = $tenant->branches()->create(['name' => 'Central', 'active' => true]);
        $courier = User::factory()->create();
        $tenant->users()->attach($courier->id, ['role' => 'courier']);

        return [$tenant, $branch, $courier];
    }

    private function order(Tenant $tenant, Branch $branch, ?User $courier, string $status): Order
    {
        return Order::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'courier_id' => $courier?->id,
            'status' => $status,
            'channel' => 'whatsapp',
            'total' => 25,
            'scheduled_date' => '2026-09-10',
            'scheduled_slot' => 'manana',
        ]);
    }

    public function test_login_devuelve_token(): void
    {
        [$tenant] = $this->tenantConCourier('h2o');
        $courier = $tenant->users()->first();
        $courier->update(['password' => bcrypt('secreto123')]);

        $this->postJson('/api/login', [
            'email' => $courier->email,
            'password' => 'secreto123',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'usuario' => ['id', 'nombre', 'rol'], 'negocio' => ['id', 'slug']])
            ->assertJsonPath('negocio.slug', 'h2o');
    }

    public function test_login_rechaza_credenciales_malas(): void
    {
        [$tenant] = $this->tenantConCourier('h2o');
        $courier = $tenant->users()->first();

        $this->postJson('/api/login', [
            'email' => $courier->email,
            'password' => 'mala',
        ])->assertStatus(422);
    }

    public function test_el_repartidor_solo_ve_sus_pedidos_asignados(): void
    {
        [$tenant, $branch, $courier] = $this->tenantConCourier('h2o');
        $otroCourier = User::factory()->create();
        $tenant->users()->attach($otroCourier->id, ['role' => 'courier']);

        $mio = $this->order($tenant, $branch, $courier, 'en_ruta');
        $ajeno = $this->order($tenant, $branch, $otroCourier, 'en_ruta');
        $entregado = $this->order($tenant, $branch, $courier, 'entregado'); // no debe salir

        Sanctum::actingAs($courier);

        $response = $this->getJson('/api/orders')->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mio->id));
        $this->assertFalse($ids->contains($ajeno->id));
        $this->assertFalse($ids->contains($entregado->id));
    }

    public function test_el_repartidor_no_ve_pedidos_de_otro_tenant(): void
    {
        [$tenantA, $branchA, $courierA] = $this->tenantConCourier('a');
        [$tenantB, $branchB, $courierB] = $this->tenantConCourier('b');

        // Un pedido del tenant B, aunque tuviera el mismo courier_id, no debe cruzarse.
        $pedidoB = $this->order($tenantB, $branchB, $courierB, 'en_ruta');

        Sanctum::actingAs($courierA);

        $ids = collect($this->getJson('/api/orders')->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($pedidoB->id));
    }

    public function test_marcar_entregado(): void
    {
        [$tenant, $branch, $courier] = $this->tenantConCourier('h2o');
        $order = $this->order($tenant, $branch, $courier, 'en_ruta');

        Sanctum::actingAs($courier);

        $this->postJson("/api/orders/{$order->id}/delivered")
            ->assertOk()
            ->assertJsonPath('data.estado', 'entregado');

        $this->assertSame('entregado', $order->fresh()->status);
    }

    public function test_marcar_entregado_descuenta_el_stock(): void
    {
        [$tenant, $branch, $courier] = $this->tenantConCourier('h2o');
        $producto = \App\Models\Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Bidón 20L', 'price' => 25, 'unit' => 'bidón',
        ]);
        \App\Models\StockLevel::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id,
            'product_id' => $producto->id, 'quantity' => 10,
        ]);

        $order = $this->order($tenant, $branch, $courier, 'en_ruta');
        \App\Models\OrderItem::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'order_id' => $order->id, 'product_id' => $producto->id,
            'product_name' => 'Bidón 20L', 'quantity' => 4, 'unit_price_list' => 25,
        ]);

        Sanctum::actingAs($courier);
        $this->postJson("/api/orders/{$order->id}/delivered")->assertOk();

        $qty = \App\Models\StockLevel::withoutGlobalScopes()
            ->where('branch_id', $branch->id)->where('product_id', $producto->id)->value('quantity');
        $this->assertSame(6, (int) $qty); // 10 - 4
    }

    public function test_no_puede_marcar_entregado_un_pedido_ajeno(): void
    {
        [$tenant, $branch, $courier] = $this->tenantConCourier('h2o');
        $otro = User::factory()->create();
        $tenant->users()->attach($otro->id, ['role' => 'courier']);

        $ajeno = $this->order($tenant, $branch, $otro, 'en_ruta');

        Sanctum::actingAs($courier);

        $this->postJson("/api/orders/{$ajeno->id}/delivered")->assertNotFound();
        $this->assertSame('en_ruta', $ajeno->fresh()->status);
    }

    public function test_registrar_token_de_push(): void
    {
        [$tenant, , $courier] = $this->tenantConCourier('h2o');

        Sanctum::actingAs($courier);

        $this->postJson('/api/device-tokens', [
            'token' => 'ExponentPushToken[abc123]',
            'platform' => 'android',
        ])->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'token' => 'ExponentPushToken[abc123]',
            'user_id' => $courier->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_sin_token_no_se_accede_a_pedidos(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
    }
}
