<?php

namespace Tests\Support;

use App\Services\Push\PushNotifier;

/**
 * Notificador push falso para tests: no toca la red; registra los envíos.
 */
class FakePushNotifier implements PushNotifier
{
    /** @var array<int, array{tokens: array<int, string>, title: string, body: string, data: array<string, mixed>}> */
    public array $sent = [];

    public function send(array $tokens, string $title, string $body, array $data = []): void
    {
        $tokens = array_values(array_filter($tokens));
        if (empty($tokens)) {
            return;
        }

        $this->sent[] = compact('tokens', 'title', 'body', 'data');
    }
}
