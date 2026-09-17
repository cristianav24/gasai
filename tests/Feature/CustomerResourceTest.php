<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerResourceTest extends TestCase
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

    public function test_la_lista_muestra_a_los_clientes(): void
    {
        Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Cristian', 'phone' => '+51941649964']);

        Livewire::test(ListCustomers::class)
            ->assertOk()
            ->assertSee('Cristian')
            ->assertSee('+51941649964');
    }

    public function test_la_lista_solo_muestra_clientes_del_negocio_actual(): void
    {
        Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'MiCliente']);
        $otro = Tenant::create(['name' => 'Otro', 'slug' => 'otro', 'rubro' => 'gas']);
        Customer::withoutGlobalScopes()->create(['tenant_id' => $otro->id, 'name' => 'ClienteAjeno']);

        Livewire::test(ListCustomers::class)
            ->assertOk()
            ->assertSee('MiCliente')
            ->assertDontSee('ClienteAjeno');
    }

    public function test_la_ficha_muestra_datos_direcciones_y_pedidos(): void
    {
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Cristian', 'phone' => '+51999']);
        $customer->addresses()->create(['tenant_id' => $this->tenant->id, 'address' => 'Jr. Gonzales Prada 753', 'lat' => -12.05, 'lng' => -75.23]);
        Order::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'customer_id' => $customer->id,
            'number' => 1000, 'status' => 'pendiente', 'channel' => 'whatsapp', 'total' => 13,
        ]);

        Livewire::test(ViewCustomer::class, ['record' => $customer->getRouteKey()])
            ->assertOk()
            ->assertSee('Cristian')
            ->assertSee('Jr. Gonzales Prada 753')
            ->assertSee('#01000'); // número de pedido formateado (padding 5 por defecto)
    }
}
