<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappAccount;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

/**
 * Implementación real contra la WhatsApp Cloud API de Meta (graph.facebook.com).
 */
class CloudApiGateway implements WhatsAppGateway
{
    public function __construct(
        private HttpFactory $http,
        private string $graphUrl,
        private string $graphVersion,
    ) {}

    public function sendText(WhatsappAccount $account, string $recipient, string $text): array
    {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'type' => 'text',
                'text' => ['body' => $text],
            ];

            // WhatsApp usernames: si el cliente oculta su número, el destinatario
            // es un BSUID (ej. "PE.123...") y va en el campo 'recipient', no en 'to'
            // (que espera un teléfono +51...). Chatwoot y otros hacen lo mismo.
            if ($this->isPhoneNumber($recipient)) {
                $payload['to'] = $recipient;
            } else {
                $payload['recipient'] = $recipient;
            }

            $response = $this->http
                ->baseUrl($this->endpoint($account->phone_number_id))
                ->withToken($account->access_token)
                ->acceptJson()
                ->post('/messages', $payload);

            if ($response->failed()) {
                return ['ok' => false, 'error' => "Meta respondió {$response->status()}: " . $response->body()];
            }

            return [
                'ok' => true,
                'wa_message_id' => $response->json('messages.0.id'),
            ];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function verifyCredentials(WhatsappAccount $account): array
    {
        try {
            // Consulta simple del número: si las credenciales sirven, responde 200.
            $response = $this->http
                ->baseUrl("{$this->graphUrl}/{$this->graphVersion}")
                ->withToken($account->access_token)
                ->acceptJson()
                ->get("/{$account->phone_number_id}");

            if ($response->failed()) {
                return ['ok' => false, 'error' => "Meta respondió {$response->status()}: " . $response->body()];
            }

            return ['ok' => true];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function endpoint(string $phoneNumberId): string
    {
        return "{$this->graphUrl}/{$this->graphVersion}/{$phoneNumberId}";
    }

    /**
     * ¿El destinatario es un número de teléfono (E.164) y no un BSUID?
     * Teléfono: opcional "+" seguido solo de dígitos. BSUID: "PAIS.alfanumérico".
     */
    private function isPhoneNumber(string $recipient): bool
    {
        return (bool) preg_match('/^\+?\d{6,15}$/', $recipient);
    }
}
