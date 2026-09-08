<?php

namespace App\Http\Controllers;

use App\Services\WhatsApp\WhatsAppInboundService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Webhook único de WhatsApp. La verificación (GET) usa el verify token; la
 * recepción (POST) delega al servicio, que resuelve el tenant por phone_number_id.
 */
class WhatsAppWebhookController extends Controller
{
    public function __construct(private WhatsAppInboundService $inbound) {}

    /** Verificación del webhook (Meta hace un GET al configurarlo). */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            return response((string) $challenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /** Recepción de mensajes. Siempre responde 200 rápido para que Meta no reintente. */
    public function receive(Request $request): Response
    {
        if (! $this->signatureValid($request)) {
            return response('Invalid signature', 403);
        }

        $this->inbound->handlePayload($request->all());

        return response('EVENT_RECEIVED', 200);
    }

    /**
     * Valida X-Hub-Signature-256 si hay app secret configurado. Sin app secret,
     * la validación queda desactivada (decisión del MVP).
     */
    private function signatureValid(Request $request): bool
    {
        $appSecret = config('services.whatsapp.app_secret');

        if (blank($appSecret)) {
            return true; // Firma opcional: sin secret, no validamos.
        }

        $signature = $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, $signature);
    }
}
