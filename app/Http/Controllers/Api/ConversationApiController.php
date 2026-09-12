<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\WhatsappAccount;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Conversaciones para la app móvil (dueños y operadores). Acotado al negocio del
 * usuario; los repartidores no acceden aquí.
 */
class ConversationApiController extends Controller
{
    /** Resuelve el negocio del usuario y valida que sea dueño u operador. */
    private function tenantFor(Request $request): Tenant
    {
        $tenant = $request->user()->tenants()->first();
        abort_if($tenant === null, 403, 'No perteneces a ningún negocio.');

        $role = $request->user()->roleIn($tenant);
        abort_unless(in_array($role, ['owner', 'operator'], true), 403, 'Sin acceso a conversaciones.');

        return $tenant;
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $this->tenantFor($request);

        $conversaciones = Conversation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with(['customer', 'lastMessage'])
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->map(fn (Conversation $c): array => [
                'id' => $c->id,
                'contacto' => $c->contactLabel(),
                'telefono' => $c->phone,
                'estado' => $c->status,
                'ultimo_mensaje' => Str::limit((string) $c->lastMessage?->content, 60),
                'actualizado' => $c->last_activity_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $conversaciones]);
    }

    public function show(Request $request, int $conversation): JsonResponse
    {
        $tenant = $this->tenantFor($request);

        $c = Conversation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with('customer')
            ->findOrFail($conversation);

        $mensajes = Message::withoutGlobalScopes()
            ->where('conversation_id', $c->id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id')
            ->get(['role', 'content', 'created_at'])
            ->map(fn (Message $m): array => [
                'rol' => $m->role,
                'contenido' => (string) $m->content,
                'hora' => $m->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => [
            'id' => $c->id,
            'contacto' => $c->contactLabel(),
            'estado' => $c->status,
            'mensajes' => $mensajes,
        ]]);
    }

    public function reply(Request $request, int $conversation, WhatsAppGateway $gateway): JsonResponse
    {
        $tenant = $this->tenantFor($request);

        $data = $request->validate(['mensaje' => ['required', 'string', 'max:4000']]);
        $texto = trim($data['mensaje']);

        $c = Conversation::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($conversation);

        // Al responder desde la app, un humano toma el control.
        if ($c->status !== 'humano') {
            $c->update(['status' => 'humano', 'assigned_user_id' => $request->user()->id]);
        }

        $c->messages()->create([
            'tenant_id' => $c->tenant_id,
            'role' => 'assistant',
            'content' => $texto,
        ]);
        $c->update(['last_activity_at' => now()]);

        $enviado = false;
        $aviso = null;
        if ($c->channel === 'whatsapp') {
            if (! $c->within24hWindow()) {
                $aviso = 'Fuera de la ventana de 24h: el mensaje quedó registrado pero WhatsApp no permite texto libre.';
            } else {
                $account = WhatsappAccount::withoutGlobalScopes()->where('tenant_id', $c->tenant_id)->first();
                $recipient = $c->phone ?: $c->wa_user_id;
                if ($account && filled($recipient)) {
                    $res = $gateway->sendText($account, $recipient, $texto);
                    $enviado = (bool) ($res['ok'] ?? false);
                    if (! $enviado) {
                        $aviso = $res['error'] ?? 'No se pudo enviar por WhatsApp.';
                    }
                }
            }
        }

        \App\Events\ConversationUpdated::dispatch($c->tenant_id, $c->id);

        return response()->json(['ok' => true, 'enviado' => $enviado, 'aviso' => $aviso]);
    }
}
