<?php

namespace Tests\Feature;

use App\Filament\Pages\Onboarding;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El middleware EnsureOnboardingCompleted lleva al wizard mientras el tenant no
 * haya terminado, y deja pasar una vez completado.
 */
class OnboardingRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => 'owner']);

        return $user;
    }

    public function test_redirige_al_wizard_si_no_completo_el_onboarding(): void
    {
        $tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $user = $this->makeOwner($tenant);

        $onboardingUrl = Onboarding::getUrl(tenant: $tenant);

        $this->actingAs($user)
            ->get("/admin/{$tenant->slug}")
            ->assertRedirect($onboardingUrl);
    }

    public function test_no_redirige_si_ya_completo_el_onboarding(): void
    {
        $tenant = Tenant::create([
            'name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua',
            'onboarding_completed_at' => now(),
        ]);
        $user = $this->makeOwner($tenant);

        $this->actingAs($user)
            ->get("/admin/{$tenant->slug}")
            ->assertOk();
    }
}
