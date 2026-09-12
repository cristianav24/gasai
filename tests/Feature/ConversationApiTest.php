<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeWhatsAppGateway;
use Tests\TestCase;

class ConversationApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;
    private FakeWhatsAppGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $this->owner = User::factory()->create();
        $this->tenant->users()->attach($this->owner->id, ['role' => 'owner']);

        $this->gateway = new FakeWhatsAppGateway;
        $this->app->instance(WhatsAppGateway::class, $this->gateway);
    }

    private function conv(?Tenant $t = null, array $attrs = []): Conversation
    {
        return Conversation::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => ($t ?? $this->tenant)->id,
            'channel' => 'whatsapp', 'status' => 'bot',
            'phone' => '+51999888777', 'contact_name' => 'Javier',
            'last_inbound_at' => now(), 'last_activity_at' => now(),
        ], $attrs));
    }

    public function test_el_dueno_lista_las_conversaciones_de_su_negocio(): void
    {
        $mia = $this->conv();
        $otro = Tenant::create(['name' => 'Otro', 'slug' => 'otro', 'rubro' => 'gas']);
        $this->conv($otro, ['contact_name' => 'Ajeno']);

        Sanctum::actingAs($this->owner);

        $this->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mia->id)
            ->assertJsonPath('data.0.contacto', 'Javier');
    }

    public function test_un_repartidor_no_accede_a_conversaciones(): void
    {
        $courier = User::factory()->create();
        $this->tenant->users()->attach($courier->id, ['role' => 'courier']);
        $this->conv();

        Sanctum::actingAs($courier);

        $this->getJson('/api/conversations')->assertForbidden();
    }

    public function test_show_devuelve_el_hilo(): void
    {
        $c = $this->conv();
        $c->messages()->create(['tenant_id' => $this->tenant->id, 'role' => 'user', 'content' => 'Hola']);
        $c->messages()->create(['tenant_id' => $this->tenant->id, 'role' => 'assistant', 'content' => '¡Hola! ¿Qué necesitas?']);

        Sanctum::actingAs($this->owner);

        $this->getJson("/api/conversations/{$c->id}")
            ->assertOk()
            ->assertJsonPath('data.contacto', 'Javier')
            ->assertJsonCount(2, 'data.mensajes')
            ->assertJsonPath('data.mensajes.0.contenido', 'Hola');
    }

    public function test_responder_toma_el_control_y_envia_por_whatsapp(): void
    {
        WhatsappAccount::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'phone_number_id' => 'P1', 'access_token' => 't', 'status' => 'connected',
        ]);
        $c = $this->conv();

        Sanctum::actingAs($this->owner);

        $this->postJson("/api/conversations/{$c->id}/reply", ['mensaje' => 'Te atiendo yo'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('enviado', true);

        $this->assertSame('humano', $c->fresh()->status);
        $this->assertDatabaseHas('messages', ['conversation_id' => $c->id, 'role' => 'assistant', 'content' => 'Te atiendo yo']);
        $this->assertCount(1, $this->gateway->sent);
    }
}
