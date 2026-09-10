<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\WhatsappAccount;
use App\Services\Agent\AgentContext;
use App\Services\Agent\AgentService;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Procesa los mensajes entrantes sin procesar de una conversación de WhatsApp:
 * corre el agente una vez (agrupando el debounce) y envía la respuesta.
 */
class ProcessIncomingWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $conversationId,
        public ?string $contactName = null,
    ) {}

    public function handle(AgentService $agent, WhatsAppGateway $gateway): void
    {
        $conversation = Conversation::withoutGlobalScopes()->find($this->conversationId);

        if (! $conversation) {
            return;
        }

        // Debounce: si llegó un mensaje más nuevo hace muy poco, dejamos que el
        // job de ese mensaje procese todo junto.
        $debounce = (int) config('services.whatsapp.debounce_seconds', 5);
        if ($conversation->last_inbound_at
            && $conversation->last_inbound_at->gt(now()->subSeconds($debounce)->addSecond())) {
            return;
        }

        // Mensajes entrantes aún sin procesar.
        $pendientes = Message::withoutGlobalScopes()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'user')
            ->whereNull('processed_at')
            ->orderBy('id')
            ->get();

        if ($pendientes->isEmpty()) {
            return;
        }

        // Los marcamos procesados antes de responder (evita doble procesamiento).
        Message::withoutGlobalScopes()
            ->whereIn('id', $pendientes->pluck('id'))
            ->update(['processed_at' => now()]);

        // Si un humano tomó el control, el bot no responde.
        if ($conversation->status === 'humano') {
            return;
        }

        $tenant = Tenant::find($conversation->tenant_id);
        if (! $tenant) {
            return;
        }

        $customer = null;
        if ($conversation->customer_id) {
            $customer = Customer::withoutGlobalScopes()->find($conversation->customer_id);
        } elseif ($conversation->wa_user_id || $conversation->phone) {
            $customer = Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where(function ($q) use ($conversation): void {
                    if ($conversation->wa_user_id) {
                        $q->orWhere('wa_user_id', $conversation->wa_user_id);
                    }
                    if ($conversation->phone) {
                        $q->orWhere('phone', $conversation->phone);
                    }
                })
                ->first();
        }

        $context = new AgentContext($tenant, $conversation, $customer);

        $reply = $agent->respond($context);

        $this->sendReply($conversation, $reply, $gateway);
    }

    private function sendReply(Conversation $conversation, string $reply, WhatsAppGateway $gateway): void
    {
        // Ventana de 24 h: fuera de ella solo se permiten plantillas (no en el MVP).
        if (! $conversation->fresh()->within24hWindow()) {
            Log::warning('WhatsApp: respuesta no enviada, fuera de la ventana de 24h.', [
                'conversation_id' => $conversation->id,
            ]);

            return;
        }

        $account = WhatsappAccount::withoutGlobalScopes()
            ->where('tenant_id', $conversation->tenant_id)
            ->first();

        if (! $account) {
            Log::warning('WhatsApp: sin cuenta conectada para el tenant.', [
                'tenant_id' => $conversation->tenant_id,
            ]);

            return;
        }

        // Destinatario: el teléfono si lo tenemos, si no el identificador estable
        // (BSUID) — el cliente puede tener su número oculto (usernames de WhatsApp).
        $recipient = $conversation->phone ?: $conversation->wa_user_id;

        if (blank($recipient)) {
            Log::warning('WhatsApp: sin destinatario para responder.', ['conversation_id' => $conversation->id]);

            return;
        }

        $result = $gateway->sendText($account, $recipient, $reply);

        if (! ($result['ok'] ?? false)) {
            Log::error('WhatsApp: falló el envío.', [
                'conversation_id' => $conversation->id,
                'recipient' => $recipient,
                'error' => $result['error'] ?? 'desconocido',
            ]);
        }
    }
}
