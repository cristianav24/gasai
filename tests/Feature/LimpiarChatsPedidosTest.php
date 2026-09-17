<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LimpiarChatsPedidosTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $this->branch = $this->tenant->branches()->create(['name' => 'Central', 'active' => true]);
    }

    private function seedData(): Customer
    {
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Cristian', 'phone' => '+51999']);
        $customer->addresses()->create([
            'tenant_id' => $this->tenant->id, 'address' => 'Jr. X 123', 'lat' => -12.0, 'lng' => -75.0,
        ]);
        $conv = Conversation::create(['tenant_id' => $this->tenant->id, 'channel' => 'whatsapp', 'status' => 'bot', 'customer_id' => $customer->id]);
        $conv->messages()->create(['tenant_id' => $this->tenant->id, 'role' => 'user', 'content' => 'hola']);
        Order::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'customer_id' => $customer->id,
            'status' => 'pendiente', 'channel' => 'whatsapp', 'total' => 10,
        ]);

        return $customer;
    }

    public function test_sin_clientes_borra_chats_y_pedidos_pero_conserva_clientes(): void
    {
        $this->seedData();

        $this->artisan('gasai:limpiar-chats-pedidos', ['--tenant' => 'h2o', '--force' => true])
            ->assertSuccessful();

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
        $this->assertSame(0, Conversation::withoutGlobalScopes()->withTrashed()->count());
        $this->assertSame(1, Customer::withoutGlobalScopes()->count()); // se conserva
    }

    public function test_con_clientes_borra_tambien_clientes_y_sus_direcciones(): void
    {
        $this->seedData();

        $this->artisan('gasai:limpiar-chats-pedidos', ['--tenant' => 'h2o', '--clientes' => true, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(0, Customer::withoutGlobalScopes()->count());
        $this->assertSame(0, \App\Models\Address::withoutGlobalScopes()->count()); // cascada
    }

    public function test_todo_borra_ventas_stock_y_bidones_pero_conserva_config(): void
    {
        $this->seedData();

        // Datos transaccionales adicionales.
        \App\Models\Sale::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
            'status' => 'cobrada', 'subtotal' => 10, 'total' => 10, 'paid_at' => now(),
        ]);
        \App\Models\StockLevel::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
            'product_id' => \App\Models\Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Bidón', 'price' => 10, 'unit' => 'u'])->id,
            'quantity' => 50,
        ]);
        $tipo = \App\Models\ContainerType::create(['tenant_id' => $this->tenant->id, 'name' => 'Bidón 20L', 'active' => true]);
        \App\Models\ContainerStock::create(['tenant_id' => $this->tenant->id, 'container_type_id' => $tipo->id, 'full_count' => 20]);

        $this->artisan('gasai:limpiar-chats-pedidos', ['--tenant' => 'h2o', '--todo' => true, '--force' => true])
            ->assertSuccessful();

        // Transaccional: vacío.
        $this->assertSame(0, \App\Models\Sale::withoutGlobalScopes()->count());
        $this->assertSame(0, \App\Models\StockLevel::withoutGlobalScopes()->count());
        $this->assertSame(0, \App\Models\ContainerStock::withoutGlobalScopes()->count());
        $this->assertSame(0, Customer::withoutGlobalScopes()->count());
        $this->assertSame(0, Order::withoutGlobalScopes()->count());

        // Configuración: se conserva.
        $this->assertSame(1, \App\Models\Product::withoutGlobalScopes()->count());
        $this->assertSame(1, \App\Models\ContainerType::withoutGlobalScopes()->count());
    }
}
