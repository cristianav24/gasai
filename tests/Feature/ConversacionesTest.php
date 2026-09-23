<?php

namespace Tests\Feature;

use App\Filament\Pages\Conversaciones;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Services\WhatsApp\WhatsAppGateway;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FakeWhatsAppGateway;
use Tests\TestCase;

class ConversacionesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $operator;
    private FakeWhatsAppGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $this->operator = User::factory()->create();
        $this->tenant->users()->attach($this->operator->id, ['role' => 'operator']);
        $this->actingAs($this->operator);
        Filament::setTenant($this->tenant, isQuiet: true);

        $this->gateway = new FakeWhatsAppGateway;
        $this->app->instance(WhatsAppGateway::class, $this->gateway);
    }

    private function conversation(string $channel = 'whatsapp', bool $recentInbound = true): Conversation
    {
        return Conversation::create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+51987654321',
            'channel' => $channel,
            'status' => 'bot',
            'last_inbound_at' => $recentInbound ? now() : now()->subHours(30),
            'last_activity_at' => now(),
        ]);
    }

    public function test_tomar_control_pasa_a_humano_y_asigna_al_operador(): void
    {
        $c = $this->conversation();

        Livewire::test(Conversaciones::class)
            ->call('select', $c->id)
            ->call('takeControl');

        $c->refresh();
        $this->assertSame('humano', $c->status);
        $this->assertSame($this->operator->id, $c->assigned_user_id);
    }

    public function test_devolver_al_agente_restaura_el_modo_bot(): void
    {
        $c = $this->conversation();
        $c->update(['status' => 'humano', 'assigned_user_id' => $this->operator->id]);

        Livewire::test(Conversaciones::class)
            ->call('select', $c->id)
            ->call('returnToBot');

        $c->refresh();
        $this->assertSame('bot', $c->status);
        $this->assertNull($c->assigned_user_id);
    }

    public function test_el_operador_envia_un_mensaje_y_se_manda_por_whatsapp(): void
    {
        WhatsappAccount::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'phone_number_id' => 'PHONE_1',
            'access_token' => 'token',
            'status' => 'connected',
        ]);

        $c = $this->conversation();
        $c->update(['status' => 'humano', 'assigned_user_id' => $this->operator->id]);

        Livewire::test(Conversaciones::class)
            ->call('select', $c->id)
            ->set('draft', 'Hola, te atiendo yo directamente.')
            ->call('sendMessage');

        // Se registró como saliente (assistant) y se envió por el gateway.
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $c->id,
            'role' => 'assistant',
            'content' => 'Hola, te atiendo yo directamente.',
        ]);
        $this->assertCount(1, $this->gateway->sent);
        $this->assertSame('+51987654321', $this->gateway->sent[0]['to']);
    }

    public function test_fuera_de_ventana_registra_pero_no_envia(): void
    {
        WhatsappAccount::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'phone_number_id' => 'PHONE_1', 'access_token' => 'token', 'status' => 'connected',
        ]);

        $c = $this->conversation(recentInbound: false); // último entrante hace 30h
        $c->update(['status' => 'humano', 'assigned_user_id' => $this->operator->id]);

        Livewire::test(Conversaciones::class)
            ->call('select', $c->id)
            ->set('draft', 'Mensaje tardío')
            ->call('sendMessage');

        $this->assertDatabaseHas('messages', ['conversation_id' => $c->id, 'content' => 'Mensaje tardío']);
        $this->assertCount(0, $this->gateway->sent); // no se envió
    }

    public function test_toggle_minimiza_y_restaura_la_lista(): void
    {
        Livewire::test(Conversaciones::class)
            ->assertSet('listCollapsed', false)
            ->call('toggleList')
            ->assertSet('listCollapsed', true)
            ->call('toggleList')
            ->assertSet('listCollapsed', false);
    }

    public function test_eliminar_conversacion_la_oculta_de_la_bandeja(): void
    {
        $c = $this->conversation();

        $comp = Livewire::test(Conversaciones::class);
        $this->assertTrue($comp->instance()->conversations()->contains('id', $c->id));

        $comp->call('deleteConversation', $c->id)
            ->assertSet('selectedId', null);

        $this->assertSoftDeleted('conversations', ['id' => $c->id]);
        $this->assertFalse(
            Livewire::test(Conversaciones::class)->instance()->conversations()->contains('id', $c->id)
        );
    }

    public function test_el_hilo_carga_por_tandas_y_loadmore_trae_los_antiguos(): void
    {
        $c = $this->conversation();
        // 90 mensajes: más que una tanda (40), para probar la paginación.
        for ($i = 1; $i <= 90; $i++) {
            Message::create([
                'tenant_id' => $this->tenant->id,
                'conversation_id' => $c->id,
                'role' => $i % 2 ? 'user' : 'assistant',
                'content' => "msg {$i}",
            ]);
        }

        $comp = Livewire::test(Conversaciones::class)->call('select', $c->id);
        $inst = $comp->instance();

        // Primera tanda: solo los últimos 40, y en orden cronológico.
        $this->assertCount(40, $inst->thread());
        $this->assertTrue($inst->hasMoreMessages());
        $this->assertSame('msg 51', $inst->thread()->first()->content);
        $this->assertSame('msg 90', $inst->thread()->last()->content);

        // loadMore trae 40 más; ya solo quedan hasta el 90 desde el 11.
        $comp->call('loadMore');
        $inst = $comp->instance();
        $this->assertCount(80, $inst->thread());
        $this->assertTrue($inst->hasMoreMessages());

        // Otra tanda cubre los 90; ya no quedan anteriores.
        $comp->call('loadMore');
        $inst = $comp->instance();
        $this->assertCount(90, $inst->thread());
        $this->assertFalse($inst->hasMoreMessages());
        $this->assertSame('msg 1', $inst->thread()->first()->content);
    }

    public function test_seleccionar_otra_conversacion_reinicia_la_paginacion(): void
    {
        $c1 = $this->conversation();
        for ($i = 1; $i <= 60; $i++) {
            Message::create(['tenant_id' => $this->tenant->id, 'conversation_id' => $c1->id, 'role' => 'user', 'content' => "a{$i}"]);
        }

        $comp = Livewire::test(Conversaciones::class)->call('select', $c1->id)->call('loadMore');
        $this->assertSame(80, $comp->get('threadLimit'));

        $comp->call('select', $c1->id); // reabrir vuelve a la primera tanda
        $this->assertSame(40, $comp->get('threadLimit'));
    }

    public function test_la_bandeja_solo_muestra_conversaciones_del_tenant(): void
    {
        $mia = $this->conversation();

        $otro = Tenant::create(['name' => 'Otro', 'slug' => 'otro', 'rubro' => 'gas']);
        Conversation::withoutGlobalScopes()->create([
            'tenant_id' => $otro->id, 'phone' => '+51900000000', 'channel' => 'whatsapp', 'status' => 'bot',
        ]);

        $ids = Livewire::test(Conversaciones::class)->instance()->conversations()->pluck('id');
        $this->assertTrue($ids->contains($mia->id));
        $this->assertSame(1, $ids->count());
    }
}
