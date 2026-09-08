<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\WhatsappAccount;
use App\Services\Llm\LlmProvider;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeLlmProvider;
use Tests\Support\FakeWhatsAppGateway;
use Tests\TestCase;

class ProcessIncomingWhatsAppMessageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private FakeWhatsAppGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua']);
        $this->tenant->branches()->create(['name' => 'Central', 'active' => true]);
        WhatsappAccount::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'phone_number_id' => 'PHONE_1',
            'access_token' => 'token-x',
            'status' => 'connected',
        ]);

        // Contexto de tenant para crear datos de prueba (productos, etc.).
        \Filament\Facades\Filament::setTenant($this->tenant, isQuiet: true);

        $this->gateway = new FakeWhatsAppGateway;
        $this->app->instance(WhatsAppGateway::class, $this->gateway);
    }

    private function fakeLlm(FakeLlmProvider $fake): void
    {
        $this->app->instance(LlmProvider::class, $fake);
    }

    private function conversacionConEntrante(string $texto, ?int $inboundHaceSegundos = 10): Conversation
    {
        $conversation = Conversation::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+51987654321',
            'channel' => 'whatsapp',
            'status' => 'bot',
            'last_inbound_at' => now()->subSeconds($inboundHaceSegundos),
        ]);

        Message::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $texto,
            'wa_message_id' => 'wamid.' . uniqid(),
        ]);

        return $conversation;
    }

    public function test_el_job_responde_y_envia_por_el_gateway(): void
    {
        Product::create(['name' => 'Bidón 20L', 'price' => 25, 'unit' => 'bidón']);
        $this->fakeLlm(new FakeLlmProvider(FakeLlmProvider::text('¡Hola! Tenemos Bidón 20L a S/ 25.')));

        $conversation = $this->conversacionConEntrante('Hola, ¿qué venden?');

        (new ProcessIncomingWhatsAppMessage($conversation->id))
            ->handle(app(\App\Services\Agent\AgentService::class), $this->gateway);

        $this->assertCount(1, $this->gateway->sent);
        $this->assertSame('+51987654321', $this->gateway->sent[0]['to']);
        $this->assertStringContainsString('Bidón 20L', $this->gateway->sent[0]['text']);

        // El mensaje entrante quedó marcado como procesado.
        $this->assertNotNull(
            Message::withoutGlobalScopes()->where('role', 'user')->first()->processed_at
        );
    }

    public function test_el_debounce_pospone_si_llego_un_mensaje_muy_reciente(): void
    {
        $this->fakeLlm(new FakeLlmProvider(FakeLlmProvider::text('respuesta')));

        // last_inbound_at = ahora mismo → hay un mensaje más nuevo asentándose.
        $conversation = $this->conversacionConEntrante('Hola', inboundHaceSegundos: 0);

        (new ProcessIncomingWhatsAppMessage($conversation->id))
            ->handle(app(\App\Services\Agent\AgentService::class), $this->gateway);

        // No respondió aún: el job del último mensaje se encargará.
        $this->assertCount(0, $this->gateway->sent);
    }

    public function test_no_envia_fuera_de_la_ventana_de_24h(): void
    {
        Product::create(['name' => 'Bidón 20L', 'price' => 25, 'unit' => 'bidón']);
        $this->fakeLlm(new FakeLlmProvider(FakeLlmProvider::text('respuesta del agente')));

        // Último entrante hace 25 horas: fuera de la ventana.
        $conversation = $this->conversacionConEntrante('Hola', inboundHaceSegundos: 25 * 3600);

        (new ProcessIncomingWhatsAppMessage($conversation->id))
            ->handle(app(\App\Services\Agent\AgentService::class), $this->gateway);

        // El agente corre, pero no se envía nada por estar fuera de ventana.
        $this->assertCount(0, $this->gateway->sent);
    }

    public function test_no_responde_si_un_humano_tomo_el_control(): void
    {
        $this->fakeLlm(new FakeLlmProvider(FakeLlmProvider::text('no debería usarse')));

        $conversation = $this->conversacionConEntrante('Hola');
        $conversation->update(['status' => 'humano']);

        (new ProcessIncomingWhatsAppMessage($conversation->id))
            ->handle(app(\App\Services\Agent\AgentService::class), $this->gateway);

        $this->assertCount(0, $this->gateway->sent);
        // Aun así el entrante se marca procesado (no se reprocesa luego).
        $this->assertNotNull(Message::withoutGlobalScopes()->where('role', 'user')->first()->processed_at);
    }
}
