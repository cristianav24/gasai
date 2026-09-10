<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_negocio_con_dueno_sucursal_y_metodos_de_pago(): void
    {
        $user = User::factory()->create();

        $tenant = app(TenantProvisioner::class)->create(
            ['name' => 'Agua Pura EIRL', 'rubro' => 'agua'],
            $user,
        );

        $this->assertSame('Agua Pura EIRL', $tenant->name);
        $this->assertSame('agua-pura-eirl', $tenant->slug);
        $this->assertFalse($tenant->tracks_containers); // agua: sin envases
        $this->assertSame('owner', $user->fresh()->roleIn($tenant));

        $this->assertSame(1, Branch::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(5, PaymentMethod::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $efectivo = PaymentMethod::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Efectivo')->first();
        $this->assertTrue((bool) $efectivo->is_cash);
    }

    public function test_gas_activa_el_manejo_de_envases(): void
    {
        $user = User::factory()->create();

        $tenant = app(TenantProvisioner::class)->create(['name' => 'Gas Junín', 'rubro' => 'gas'], $user);

        $this->assertTrue($tenant->tracks_containers);
    }

    public function test_el_slug_es_unico(): void
    {
        $user = User::factory()->create();

        $a = app(TenantProvisioner::class)->create(['name' => 'H2O Wanka'], $user);
        $b = app(TenantProvisioner::class)->create(['name' => 'H2O Wanka'], $user);

        $this->assertSame('h2o-wanka', $a->slug);
        $this->assertSame('h2o-wanka-2', $b->slug);
    }
}
