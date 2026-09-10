<?php

namespace Tests\Feature;

use App\Filament\Super\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Super\Resources\Tenants\Pages\ListTenants;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuperPanelTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    public function test_super_admin_accede_al_panel_de_plataforma(): void
    {
        $super = $this->superAdmin();
        $normal = User::factory()->create(['is_super_admin' => false]);

        $panel = Filament::getPanel('super');

        $this->assertTrue($super->canAccessPanel($panel));
        $this->assertFalse($normal->canAccessPanel($panel));
    }

    public function test_lista_de_negocios_se_muestra_al_super_admin(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('super'));

        $t = Tenant::create(['name' => 'H2O Wanka', 'slug' => 'h2o-wanka', 'rubro' => 'agua']);

        Livewire::test(ListTenants::class)
            ->assertOk()
            ->assertSee('H2O Wanka');
    }

    public function test_crear_negocio_aprovisiona_tenant_y_dueno(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('super'));

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Gas Junín',
                'rubro' => 'gas',
                'tracks_containers' => true,
                'owner_name' => 'Pedro',
                'owner_email' => 'pedro@ejemplo.com',
                'owner_password' => 'secret123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tenant = Tenant::where('slug', 'gas-junin')->firstOrFail();
        $this->assertTrue($tenant->tracks_containers);

        $owner = User::firstWhere('email', 'pedro@ejemplo.com');
        $this->assertNotNull($owner);
        $this->assertSame('owner', $owner->roleIn($tenant));
        $this->assertSame(1, $tenant->branches()->count());
    }
}
