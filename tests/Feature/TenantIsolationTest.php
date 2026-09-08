<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El requisito de seguridad más importante del SaaS: un tenant nunca debe
 * poder leer datos de otro. Estos tests verifican el global scope de tenant.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithProduct(string $slug, string $productName): array
    {
        $tenant = Tenant::create([
            'name' => $slug,
            'slug' => $slug,
            'rubro' => 'agua',
        ]);

        // Creamos el producto acotado explícitamente por tenant_id, sin depender
        // del contexto, para preparar los datos de ambos tenants.
        $product = Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => $productName,
            'price' => 10,
        ]);

        return [$tenant, $product];
    }

    public function test_un_tenant_solo_ve_sus_propios_productos(): void
    {
        [$tenantA] = $this->makeTenantWithProduct('tenant-a', 'Bidón A');
        [$tenantB] = $this->makeTenantWithProduct('tenant-b', 'Bidón B');

        // Activamos el tenant A en el contexto de Filament.
        Filament::setTenant($tenantA, isQuiet: true);

        $productos = Product::all();

        $this->assertCount(1, $productos, 'El tenant A debe ver solo su producto');
        $this->assertSame('Bidón A', $productos->first()->name);
    }

    public function test_un_tenant_no_puede_leer_el_registro_de_otro_por_id(): void
    {
        [$tenantA] = $this->makeTenantWithProduct('tenant-a', 'Bidón A');
        [, $productoB] = $this->makeTenantWithProduct('tenant-b', 'Bidón B');

        Filament::setTenant($tenantA, isQuiet: true);

        // Buscar por el ID del producto del tenant B no debe devolver nada.
        $encontrado = Product::find($productoB->id);

        $this->assertNull($encontrado, 'El tenant A no debe poder leer un registro del tenant B');
    }

    public function test_al_crear_un_registro_se_asigna_el_tenant_del_contexto(): void
    {
        $tenantA = Tenant::create(['name' => 'tenant-a', 'slug' => 'tenant-a', 'rubro' => 'agua']);
        Filament::setTenant($tenantA, isQuiet: true);

        $producto = Product::create(['name' => 'Recarga 20L', 'price' => 8]);

        $this->assertSame($tenantA->id, $producto->tenant_id);
    }

    public function test_el_usuario_solo_accede_a_tenants_a_los_que_pertenece(): void
    {
        $tenantA = Tenant::create(['name' => 'tenant-a', 'slug' => 'tenant-a', 'rubro' => 'agua']);
        $tenantB = Tenant::create(['name' => 'tenant-b', 'slug' => 'tenant-b', 'rubro' => 'agua']);

        $user = User::factory()->create();
        $tenantA->users()->attach($user->id, ['role' => 'owner']);

        $this->assertTrue($user->canAccessTenant($tenantA));
        $this->assertFalse($user->canAccessTenant($tenantB));
    }
}
