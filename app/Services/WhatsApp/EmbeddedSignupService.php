<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

/**
 * Embedded Signup de Meta: intercambia el `code` que devuelve el diálogo por un
 * access token de negocio (server-to-server) y suscribe la app al WABA para
 * recibir sus webhooks. La app y el secret salen del servidor, nunca del cliente.
 */
class EmbeddedSignupService
{
    public function __construct(
        private HttpFactory $http,
        private string $graphUrl,
        private string $graphVersion,
        private ?string $appId,
        private ?string $appSecret,
    ) {}

    /**
     * Intercambia el `code` del Embedded Signup por un access token.
     *
     * @return array{ok: bool, access_token?: string, error?: string}
     */
    public function exchangeCode(string $code): array
    {
        if (blank($this->appId) || blank($this->appSecret)) {
            return ['ok' => false, 'error' => 'Falta configurar WHATSAPP_APP_ID y WHATSAPP_APP_SECRET en el servidor.'];
        }

        try {
            $response = $this->http
                ->baseUrl("{$this->graphUrl}/{$this->graphVersion}")
                ->acceptJson()
                ->get('/oauth/access_token', [
                    'client_id' => $this->appId,
                    'client_secret' => $this->appSecret,
                    'code' => $code,
                ]);

            if ($response->failed()) {
                return ['ok' => false, 'error' => "Meta respondió {$response->status()}: " . $response->body()];
            }

            $token = $response->json('access_token');
            if (blank($token)) {
                return ['ok' => false, 'error' => 'Meta no devolvió un access token.'];
            }

            return ['ok' => true, 'access_token' => $token];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Suscribe la app al WABA para que sus mensajes lleguen a nuestro webhook.
     *
     * @return array{ok: bool, error?: string}
     */
    public function subscribeApp(string $wabaId, string $accessToken): array
    {
        try {
            $response = $this->http
                ->baseUrl("{$this->graphUrl}/{$this->graphVersion}")
                ->withToken($accessToken)
                ->acceptJson()
                ->post("/{$wabaId}/subscribed_apps");

            if ($response->failed()) {
                return ['ok' => false, 'error' => "Meta respondió {$response->status()}: " . $response->body()];
            }

            return ['ok' => true];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
