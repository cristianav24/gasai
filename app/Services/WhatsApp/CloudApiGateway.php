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

    public function sendText(WhatsappAccount $account, string $toPhone, string $text): array
    {
        try {
            $response = $this->http
                ->baseUrl($this->endpoint($account->phone_number_id))
                ->withToken($account->access_token)
                ->acceptJson()
                ->post('/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $toPhone,
                    'type' => 'text',
                    'text' => ['body' => $text],
                ]);

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
}
