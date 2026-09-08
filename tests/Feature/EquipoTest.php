<?php

namespace Tests\Feature;

use App\Filament\Pages\Equipo;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EquipoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $this->owner = User::factory()->create();
        $this->tenant->users()->attach($this->owner->id, ['role' => 'owner']);

        $this->actingAs($this->owner);
        Filament::setTenant($this->tenant, isQuiet: true);
    }

    public function test_agregar_un_repartidor_nuevo(): void
    {
        Livewire::test(Equipo::class)
            ->callAction('agregar', data: [
                'name' => 'Repartidor Uno',
                'email' => 'repartidor@h2o.pe',
                'role' => 'courier',
                'password' => 'secreto123',
            ]);

        $user = User::where('email', 'repartidor@h2o.pe')->first();
        $this->assertNotNull($user);
        $this->assertSame('courier', $user->roleIn($this->tenant));
    }

    public function test_cambiar_el_rol_de_un_miembro(): void
    {
        $courier = User::factory()->create();
        $this->tenant->users()->attach($courier->id, ['role' => 'courier']);

        Livewire::test(Equipo::class)->call('changeRole', $courier->id, 'operator');

        $this->assertSame('operator', $courier->roleIn($this->tenant));
    }

    public function test_no_se_puede_quitar_al_unico_dueno(): void
    {
        Livewire::test(Equipo::class)->call('removeMember', $this->owner->id);

        // Sigue perteneciendo al tenant.
        $this->assertTrue($this->owner->fresh()->canAccessTenant($this->tenant));
    }

    public function test_quitar_a_un_repartidor(): void
    {
        $courier = User::factory()->create();
        $this->tenant->users()->attach($courier->id, ['role' => 'courier']);

        Livewire::test(Equipo::class)->call('removeMember', $courier->id);

        $this->assertFalse($courier->fresh()->canAccessTenant($this->tenant));
    }
}
