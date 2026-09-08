<?php

namespace Tests\Support;

use App\Models\WhatsappAccount;
use App\Services\WhatsApp\WhatsAppGateway;

/**
 * Gateway de WhatsApp falso para tests: no llama a Meta; registra los envíos.
 */
class FakeWhatsAppGateway implements WhatsAppGateway
{
    /** @var array<int, array{to: string, text: string}> */
    public array $sent = [];

    public bool $credentialsValid = true;

    public function sendText(WhatsappAccount $account, string $toPhone, string $text): array
    {
        $this->sent[] = ['to' => $toPhone, 'text' => $text];

        return ['ok' => true, 'wa_message_id' => 'wamid.fake' . count($this->sent)];
    }

    public function verifyCredentials(WhatsappAccount $account): array
    {
        return $this->credentialsValid
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'Credenciales inválidas (fake).'];
    }
}
