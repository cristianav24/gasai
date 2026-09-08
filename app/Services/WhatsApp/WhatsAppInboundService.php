<?php

namespace App\Services\WhatsApp;

use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\WhatsappAccount;
use Illuminate\Support\Carbon;

/**
 * Procesa el payload entrante del webhook de WhatsApp: resuelve el tenant por
 * phone_number_id, guarda los mensajes de forma idempotente y encola el turno
 * del agente con debounce.
 *
 * Sin contexto de tenant (el webhook no está autenticado), TODO se acota con
 * tenant_id explícito.
 */
class WhatsAppInboundService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return int Número de mensajes nuevos aceptados.
     */
    public function handlePayload(array $payload): int
    {
        $nuevos = 0;

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
                if (! $phoneNumberId) {
                    continue;
                }

                // Resolución del tenant por phone_number_id (único global).
                $account = WhatsappAccount::withoutGlobalScopes()
                    ->where('phone_number_id', $phoneNumberId)
                    ->first();

                if (! $account) {
                    continue; // Nadie conectado con ese número; ignoramos.
                }

                $nombreContacto = $value['contacts'][0]['profile']['name'] ?? null;

                foreach ($value['messages'] ?? [] as $message) {
                    if (($message['type'] ?? null) !== 'text') {
                        continue; // MVP: solo texto.
                    }

                    if ($this->store($account, $message, $nombreContacto)) {
                        $nuevos++;
                    }
                }
            }
        }

        return $nuevos;
    }

    /**
     * Guarda un mensaje entrante si no existía y encola el procesamiento.
     *
     * @param  array<string, mixed>  $message
     */
    private function store(WhatsappAccount $account, array $message, ?string $nombreContacto): bool
    {
        $tenantId = $account->tenant_id;
        $waMessageId = $message['id'] ?? null;
        $fromPhone = '+' . ltrim((string) ($message['from'] ?? ''), '+');
        $text = $message['text']['body'] ?? '';

        // Idempotencia: si ya vimos este wa_message_id, no lo reprocesamos.
        if ($waMessageId && Message::withoutGlobalScopes()->where('wa_message_id', $waMessageId)->exists()) {
            return false;
        }

        $customer = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('phone', $fromPhone)
            ->first();

        // Conversación abierta por teléfono (una por contacto en el MVP).
        $conversation = Conversation::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId, 'phone' => $fromPhone, 'channel' => 'whatsapp'],
            ['status' => 'bot', 'customer_id' => $customer?->id],
        );

        $conversation->forceFill([
            'last_inbound_at' => Carbon::now(),
            'last_activity_at' => Carbon::now(),
        ])->save();

        Message::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $text,
            'wa_message_id' => $waMessageId,
            'raw_payload' => $message,
            'processed_at' => null,
        ]);

        // Debounce: agrupamos mensajes seguidos del mismo contacto.
        ProcessIncomingWhatsAppMessage::dispatch($conversation->id, $nombreContacto)
            ->delay(now()->addSeconds((int) config('services.whatsapp.debounce_seconds', 5)));

        return true;
    }
}
