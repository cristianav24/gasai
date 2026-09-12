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

                $contact = $value['contacts'][0] ?? [];

                foreach ($value['messages'] ?? [] as $message) {
                    // Ignoramos eventos que no son mensajes de un cliente
                    // (ej. reacciones, estados del sistema).
                    if (blank($message['type'] ?? null)) {
                        continue;
                    }

                    if ($this->store($account, $message, $contact)) {
                        $nuevos++;
                    }
                }
            }
        }

        return $nuevos;
    }

    /**
     * Traduce cualquier tipo de mensaje entrante a una representación de texto
     * que el agente y la bandeja pueden mostrar/usar.
     *
     * @param  array<string, mixed>  $message
     */
    private function extractContent(array $message): string
    {
        return match ($message['type'] ?? 'text') {
            'text' => (string) ($message['text']['body'] ?? ''),

            'location' => $this->locationContent($message['location'] ?? []),

            'image' => '📷 [Imagen]' . $this->caption($message['image'] ?? []),
            'video' => '🎥 [Video]' . $this->caption($message['video'] ?? []),
            'audio' => '🎙️ [Audio]',
            'document' => '📎 [Documento: ' . ($message['document']['filename'] ?? 'archivo') . ']',
            'sticker' => '🩿 [Sticker]',
            'contacts' => '👤 [Contacto compartido]',

            // Botones/listas interactivas: usamos el texto que eligió el cliente.
            'button' => (string) ($message['button']['text'] ?? '[Respuesta]'),
            'interactive' => (string) (
                $message['interactive']['button_reply']['title']
                ?? $message['interactive']['list_reply']['title']
                ?? '[Respuesta]'
            ),

            default => '[Mensaje de tipo ' . ($message['type'] ?? 'desconocido') . ']',
        };
    }

    /**
     * @param  array<string, mixed>  $location
     */
    private function locationContent(array $location): string
    {
        $lat = $location['latitude'] ?? null;
        $lng = $location['longitude'] ?? null;

        if ($lat === null || $lng === null) {
            return '📍 [Ubicación compartida]';
        }

        $extra = '';
        if (! blank($location['name'] ?? null)) {
            $extra .= ' — ' . $location['name'];
        }
        if (! blank($location['address'] ?? null)) {
            $extra .= ' (' . $location['address'] . ')';
        }

        // Incluimos las coordenadas para que el agente pueda usarlas al guardar
        // la dirección de entrega, y un enlace para que el operador las vea.
        return "📍 Ubicación compartida: {$lat},{$lng}{$extra}. "
            . "Mapa: https://maps.google.com/?q={$lat},{$lng}";
    }

    /**
     * @param  array<string, mixed>  $media
     */
    private function caption(array $media): string
    {
        return blank($media['caption'] ?? null) ? '' : ' ' . $media['caption'];
    }

    /**
     * Guarda un mensaje entrante si no existía y encola el procesamiento.
     *
     * Compatible con los usernames de WhatsApp (jun 2026): el remitente puede
     * venir como número (E.164) o como BSUID (user_id), y el contacto puede
     * traer un @username además del nombre de perfil.
     *
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $contact
     */
    private function store(WhatsappAccount $account, array $message, array $contact): bool
    {
        $tenantId = $account->tenant_id;
        $waMessageId = $message['id'] ?? null;
        // Representa el mensaje como texto según su tipo (texto, ubicación, imagen…).
        $text = $this->extractContent($message);

        // Idempotencia: si ya vimos este wa_message_id, no lo reprocesamos.
        if ($waMessageId && Message::withoutGlobalScopes()->where('wa_message_id', $waMessageId)->exists()) {
            return false;
        }

        // --- Identidad del contacto ---
        $from = trim((string) ($message['from'] ?? ''));
        $userId = $message['user_id'] ?? ($contact['user_id'] ?? null);  // BSUID
        $username = $contact['username'] ?? null;
        $profileName = $contact['profile']['name'] ?? null;              // nombre/nickname mostrado

        // Si "from" son solo dígitos, es un número; si no, es un BSUID.
        $isPhone = $from !== '' && ctype_digit($from);
        $phone = $isPhone ? '+' . $from : null;
        // Identificador estable del contacto: BSUID si viene, si no el "from".
        $waUserId = (string) ($userId ?: $from);
        $displayName = $profileName ?: ($username ? '@' . ltrim($username, '@') : null);

        // Cliente: por identificador estable, o por teléfono si lo tenemos.
        $customer = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($waUserId, $phone): void {
                $q->where('wa_user_id', $waUserId);
                if ($phone) {
                    $q->orWhere('phone', $phone);
                }
            })
            ->first();

        // Guardamos la identidad de WhatsApp en el cliente si ya existe.
        if ($customer) {
            $customer->forceFill(array_filter([
                'wa_user_id' => $customer->wa_user_id ?: $waUserId,
                'username' => $username ?: $customer->username,
                'phone' => $customer->phone ?: $phone,
            ]))->save();
        }

        // Una conversación por contacto (clave estable = wa_user_id).
        $conversation = Conversation::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->firstOrCreate(
            ['tenant_id' => $tenantId, 'wa_user_id' => $waUserId, 'channel' => 'whatsapp'],
            ['status' => 'bot', 'customer_id' => $customer?->id, 'phone' => $phone],
        );

        $conversation->forceFill([
            'phone' => $phone ?: $conversation->phone,
            'contact_name' => $displayName ?: $conversation->contact_name,
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

        // Tiempo real: avisa a la bandeja del panel que llegó un mensaje.
        \App\Events\ConversationUpdated::dispatch($tenantId, $conversation->id);

        // Push a la app: aviso de mensaje nuevo (chat nuevo o mensaje en curso).
        app(\App\Services\Push\PushDispatcher::class)
            ->notifyNewMessage($conversation, $text, $conversation->wasRecentlyCreated);

        // Debounce: agrupamos mensajes seguidos del mismo contacto.
        ProcessIncomingWhatsAppMessage::dispatch($conversation->id, $displayName)
            ->delay(now()->addSeconds((int) config('services.whatsapp.debounce_seconds', 5)));

        return true;
    }
}
