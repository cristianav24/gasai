<?php

namespace Tests\Feature;

use App\Filament\Pages\Onboarding;
use App\Filament\Widgets\OnboardingChecklist;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function actingInTenant(Tenant $tenant): void
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => 'owner']);
        $this->actingAs($user);
        Filament::setTenant($tenant, isQuiet: true);
    }

    public function test_el_paso_tu_negocio_es_obligatorio(): void
    {
        $tenant = Tenant::create(['name' => '', 'slug' => 'nuevo', 'rubro' => 'agua']);
        $this->actingInTenant($tenant);

        // Sin nombre no debe poder avanzar del primer paso.
        Livewire::test(Onboarding::class)
            ->fillForm(['name' => '', 'rubro' => 'agua', 'timezone' => 'America/Lima'])
            ->call('complete')
            ->assertHasFormErrors(['name']);

        $this->assertNull($tenant->fresh()->onboarding_completed_at);
    }

    public function test_completar_marca_el_onboarding_y_guarda_el_negocio(): void
    {
        $tenant = Tenant::create(['name' => 'Sin nombre', 'slug' => 'h2o', 'rubro' => 'agua']);
        $this->actingInTenant($tenant);

        Livewire::test(Onboarding::class)
            ->fillForm([
                'name' => 'H2O Wanka',
                'rubro' => 'agua',
                'business_hours_text' => 'Lun a Sáb 8-18',
                'timezone' => 'America/Lima',
            ])
            ->call('complete')
            ->assertHasNoFormErrors();

        $tenant->refresh();
        $this->assertNotNull($tenant->onboarding_completed_at);
        $this->assertSame('H2O Wanka', $tenant->name);
    }

    public function test_los_ejemplos_de_productos_se_precargan_segun_el_rubro(): void
    {
        $agua = collect(Onboarding::exampleProductsFor('agua'))->pluck('name')->all();
        $gas = collect(Onboarding::exampleProductsFor('gas'))->pluck('name')->all();

        $this->assertContains('Bidón 20L nuevo', $agua);
        $this->assertContains('Recarga 20L', $agua);
        $this->assertContains('Balón 10kg', $gas);
        $this->assertSame([], Onboarding::exampleProductsFor('otro'));
    }

    public function test_el_checklist_detecta_pendientes_y_se_puede_descartar(): void
    {
        $tenant = Tenant::create([
            'name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua',
            'onboarding_completed_at' => now(),
        ]);
        $this->actingInTenant($tenant);

        Product::create(['name' => 'Bidón 20L', 'price' => 25, 'unit' => 'bidón']);

        $widget = Livewire::test(OnboardingChecklist::class);

        // Con solo un producto cargado, quedan pendientes (zonas, conocimiento, whatsapp…).
        $this->assertGreaterThan(0, $widget->instance()->getPendingCount());

        // Descartar oculta el checklist.
        $widget->call('dismiss');
        $this->assertTrue($tenant->fresh()->onboarding_checklist_dismissed);
        $this->assertFalse(OnboardingChecklist::canView());
    }
}
