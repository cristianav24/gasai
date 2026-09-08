<?php

namespace App\Services\Push;

/**
 * Envío de notificaciones push. Abstraído para poder cambiar de proveedor
 * (Expo, FCM directo, etc.) sin tocar quien lo usa.
 */
interface PushNotifier
{
    /**
     * @param  array<int, string>  $tokens  Tokens de push de los dispositivos destino.
     * @param  array<string, mixed>  $data   Datos extra (ej. pedido_id para abrir la pantalla).
     */
    public function send(array $tokens, string $title, string $body, array $data = []): void;
}
