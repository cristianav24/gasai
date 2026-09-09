<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\WhatsappAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function tenantConNumero(string $phoneNumberId): Tenant
    {
        $tenant = Tenant::create(['name' => 'H2O', 'slug' => 'h2o-' . $phoneNumberId, 'rubro' => 'agua']);
        WhatsappAccount::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'phone_number_id' => $phoneNumberId,
            'access_token' => 'token-x',
            'status' => 'connected',
        ]);

        return $tenant;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $phoneNumberId, string $from, string $text, string $waId): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => $phoneNumberId],
                        'contacts' => [['profile' => ['name' => 'Juan'], 'wa_id' => $from]],
                        'messages' => [[
                            'from' => $from,
                            'id' => $waId,
                            'type' => 'text',
                            'text' => ['body' => $text],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    public function test_la_verificacion_devuelve_el_challenge_con_el_token_correcto(): void
    {
        config(['services.whatsapp.verify_token' => 'secreto-123']);

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=secreto-123&hub_challenge=ABC123')
            ->assertOk()
            ->assertSee('ABC123');
    }

    public function test_la_verificacion_rechaza_un_token_incorrecto(): void
    {
        config(['services.whatsapp.verify_token' => 'secreto-123']);

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=malo&hub_challenge=ABC123')
            ->assertForbidden();
    }

    public function test_el_webhook_resuelve_el_tenant_por_phone_number_id_y_guarda_el_mensaje(): void
    {
        Queue::fake();
        $tenant = $this->tenantConNumero('PHONE_1');

        $this->postJson('/webhooks/whatsapp', $this->payload('PHONE_1', '51987654321', 'Hola', 'wamid.1'))
            ->assertOk();

        $message = Message::withoutGlobalScopes()->firstWhere('wa_message_id', 'wamid.1');
        $this->assertNotNull($message);
        $this->assertSame($tenant->id, $message->tenant_id);
        $this->assertSame('Hola', $message->content);

        Queue::assertPushed(ProcessIncomingWhatsAppMessage::class);
    }

    public function test_un_mensaje_de_otro_tenant_no_se_mezcla(): void
    {
        Queue::fake();
        $tenantA = $this->tenantConNumero('PHONE_A');
        $tenantB = $this->tenantConNumero('PHONE_B');

        $this->postJson('/webhooks/whatsapp', $this->payload('PHONE_B', '51900000000', 'Para B', 'wamid.b1'))
            ->assertOk();

        $message = Message::withoutGlobalScopes()->firstWhere('wa_message_id', 'wamid.b1');
        $this->assertSame($tenantB->id, $message->tenant_id);
        $this->assertNotSame($tenantA->id, $message->tenant_id);
    }

    public function test_el_mismo_mensaje_no_se_duplica_idempotencia(): void
    {
        Queue::fake();
        $this->tenantConNumero('PHONE_1');

        $payload = $this->payload('PHONE_1', '51987654321', 'Hola', 'wamid.dup');

        $this->postJson('/webhooks/whatsapp', $payload)->assertOk();
        $this->postJson('/webhooks/whatsapp', $payload)->assertOk(); // reintento de Meta

        $this->assertSame(1, Message::withoutGlobalScopes()->where('wa_message_id', 'wamid.dup')->count());
        Queue::assertPushed(ProcessIncomingWhatsAppMessage::class, 1);
    }

    public function test_maneja_username_y_bsuid_sin_numero(): void
    {
        Queue::fake();
        $tenant = $this->tenantConNumero('PHONE_1');

        // Payload estilo jun-2026: "from" es un BSUID y el contacto trae username.
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => 'PHONE_1'],
                        'contacts' => [[
                            'profile' => ['name' => 'Cristian V.'],
                            'username' => 'cristian.wanka',
                            'user_id' => 'PE.9Z8Y7X6W5V',
                        ]],
                        'messages' => [[
                            'from' => 'PE.9Z8Y7X6W5V',
                            'user_id' => 'PE.9Z8Y7X6W5V',
                            'id' => 'wamid.bsuid1',
                            'type' => 'text',
                            'text' => ['body' => 'Hola sin número'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/webhooks/whatsapp', $payload)->assertOk();

        $conv = Conversation::withoutGlobalScopes()->firstWhere('wa_user_id', 'PE.9Z8Y7X6W5V');
        $this->assertNotNull($conv);
        $this->assertSame($tenant->id, $conv->tenant_id);
        $this->assertSame('Cristian V.', $conv->contact_name); // se muestra el nickname
        $this->assertNull($conv->phone);                       // no vino número
    }

    public function test_un_numero_no_conectado_se_ignora(): void
    {
        Queue::fake();

        $this->postJson('/webhooks/whatsapp', $this->payload('DESCONOCIDO', '51987654321', 'Hola', 'wamid.x'))
            ->assertOk();

        $this->assertSame(0, Message::withoutGlobalScopes()->count());
        Queue::assertNothingPushed();
    }
}
