<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 2: los CRUD de Filament respetan el aislamiento por tenant en el flujo
 * real (usuario autenticado + tenant activo del panel).
 */
class ProductResourceTest extends TestCase
{
    use RefreshDatabase;

    private function actingInTenant(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user);
        Filament::setTenant($tenant, isQuiet: true);

        return $user;
    }

    public function test_crear_un_producto_desde_filament_lo_asigna_al_tenant_activo(): void
    {
        $tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $this->actingInTenant($tenant);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Bidón 20L nuevo',
                'price' => 25,
                'unit' => 'bidón',
                'type' => 'venta',
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $producto = Product::withoutGlobalScopes()->firstWhere('name', 'Bidón 20L nuevo');

        $this->assertNotNull($producto);
        $this->assertSame($tenant->id, $producto->tenant_id);
    }

    public function test_el_listado_de_filament_no_muestra_productos_de_otro_tenant(): void
    {
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'a', 'rubro' => 'agua']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'b', 'rubro' => 'agua']);

        Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id, 'name' => 'Producto A', 'price' => 10, 'unit' => 'bidón',
        ]);
        Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id, 'name' => 'Producto B', 'price' => 10, 'unit' => 'bidón',
        ]);

        $this->actingInTenant($tenantA);

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords(Product::where('name', 'Producto A')->get())
            ->assertCanNotSeeTableRecords(
                Product::withoutGlobalScopes()->where('name', 'Producto B')->get()
            );
    }
}
