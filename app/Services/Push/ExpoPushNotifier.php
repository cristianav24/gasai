<?php

namespace App\Services\Push;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envío por el servicio de push de Expo (gratis, por debajo usa FCM/APNs).
 * Endpoint: https://exp.host/--/api/v2/push/send
 */
class ExpoPushNotifier implements PushNotifier
{
    public function __construct(
        private HttpFactory $http,
        private string $endpoint = 'https://exp.host/--/api/v2/push/send',
    ) {}

    public function send(array $tokens, string $title, string $body, array $data = []): void
    {
        // Sin destinatarios no hay nada que enviar (evita llamadas vacías/red en tests).
        $tokens = array_values(array_filter($tokens));
        if (empty($tokens)) {
            return;
        }

        // Expo acepta un arreglo de mensajes en una sola petición.
        $messages = array_map(fn (string $token): array => [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'sound' => 'default',
        ], $tokens);

        try {
            $response = $this->http
                ->acceptJson()
                ->asJson()
                ->post($this->endpoint, $messages);

            if ($response->failed()) {
                Log::warning('Expo push falló.', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
