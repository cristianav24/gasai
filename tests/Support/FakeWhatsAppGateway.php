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

    /** @var array<int, array{to: string, type: string, filename: string, caption: ?string}> */
    public array $sentMedia = [];

    public bool $credentialsValid = true;

    public function sendText(WhatsappAccount $account, string $toPhone, string $text): array
    {
        $this->sent[] = ['to' => $toPhone, 'text' => $text];

        return ['ok' => true, 'wa_message_id' => 'wamid.fake' . count($this->sent)];
    }

    public function sendMedia(
        WhatsappAccount $account,
        string $toPhone,
        string $type,
        string $absolutePath,
        string $mime,
        string $filename,
        ?string $caption = null,
    ): array {
        $this->sentMedia[] = ['to' => $toPhone, 'type' => $type, 'filename' => $filename, 'caption' => $caption];

        return ['ok' => true, 'media_id' => 'media.fake' . count($this->sentMedia), 'wa_message_id' => 'wamid.media' . count($this->sentMedia)];
    }

    public function verifyCredentials(WhatsappAccount $account): array
    {
        return $this->credentialsValid
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'Credenciales inválidas (fake).'];
    }
}
