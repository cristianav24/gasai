<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappAccount;

/**
 * Interfaz de la conexión con WhatsApp. Todo el resto del sistema (webhook, job,
 * pantalla de conexión) depende de esto, no de la Cloud API directamente. Así,
 * cuando llegue el Embedded Signup de Meta, solo cambia la implementación.
 */
interface WhatsAppGateway
{
    /**
     * Envía un mensaje de texto libre a un número.
     *
     * @return array{ok: bool, wa_message_id?: string, error?: string}
     */
    public function sendText(WhatsappAccount $account, string $toPhone, string $text): array;

    /**
     * Verifica que las credenciales de la cuenta funcionan (para el botón "probar conexión").
     *
     * @return array{ok: bool, error?: string}
     */
    public function verifyCredentials(WhatsappAccount $account): array;
}
