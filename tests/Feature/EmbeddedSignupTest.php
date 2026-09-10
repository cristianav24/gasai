<?php

namespace Tests\Feature;

use App\Filament\Pages\ConexionWhatsapp;
use App\Models\Tenant;
use App\Models\User;
use App\Services\WhatsApp\EmbeddedSignupService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class EmbeddedSignupTest extends TestCase
{
    use RefreshDatabase;

    private function service(?string $appId = 'APPID', ?string $secret = 'SECRET'): EmbeddedSignupService
    {
        return new EmbeddedSignupService(
            app(HttpFactory::class),
            'https://graph.facebook.com',
            'v21.0',
            $appId,
            $secret,
        );
    }

    public function test_intercambia_el_codigo_por_un_token(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'EAAG_token_123'], 200),
        ]);

        $res = $this->service()->exchangeCode('CODE_ABC');

        $this->assertTrue($res['ok']);
        $this->assertSame('EAAG_token_123', $res['access_token']);
    }

    public function test_sin_app_id_o_secret_no_intercambia(): void
    {
        $res = $this->service(appId: null)->exchangeCode('CODE_ABC');

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('WHATSAPP_APP_ID', $res['error']);
    }

    public function test_error_de_meta_se_reporta(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['error' => ['message' => 'bad code']], 400),
        ]);

        $res = $this->service()->exchangeCode('CODE_MALO');

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('400', $res['error']);
    }

    public function test_suscribe_la_app_al_waba(): void
    {
        Http::fake([
            'graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true], 200),
        ]);

        $res = $this->service()->subscribeApp('WABA123', 'TOKEN');

        $this->assertTrue($res['ok']);
    }

    public function test_la_pantalla_de_conexion_renderiza(): void
    {
        $tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $owner = User::factory()->create();
        $tenant->users()->attach($owner->id, ['role' => 'owner']);
        $this->actingAs($owner);
        Filament::setTenant($tenant, isQuiet: true);

        Livewire::test(ConexionWhatsapp::class)
            ->assertOk()
            ->assertSee('Estado de la conexión');
    }
}
