<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_valido_inicia_sesion_web_y_redirige_al_panel(): void
    {
        $tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => 'owner']);
        $plain = $user->createToken('app')->plainTextToken;

        $this->get('/app-login?token=' . $plain)->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
    }

    public function test_token_invalido_va_al_login(): void
    {
        $this->get('/app-login?token=noexiste')->assertRedirect('/admin/login');
        $this->assertGuest();
    }
}
