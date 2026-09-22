<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa el histórico de conversaciones de WhatsApp (exportado a JSON) al panel:
 *  - crea/enlaza la conversación de cada contacto con su cliente (por teléfono,
 *    @username o nombre) y carga sus mensajes con la fecha/hora original;
 *  - deduce la dirección del cliente a partir de lo que escribió en el chat;
 *  - estima cuántos pedidos hizo (aproximado, por señales de entrega/pago) y lo
 *    deja anotado en el cliente. NO crea pedidos ni ventas: no toca la caja.
 *
 * Es idempotente: cada mensaje se marca con wa_message_id "imp_<id>", así que
 * re-ejecutar no duplica. Las horas del export están en hora de Perú y se
 * guardan en UTC (como el resto del sistema).
 *
 * Uso: php artisan gasai:importar-whatsapp ruta.json --tenant=h2o-wanka [--dry-run]
 */
class ImportarWhatsapp extends Command
{
    protected $signature = 'gasai:importar-whatsapp {file : Ruta del JSON exportado} {--tenant=h2o-wanka : Slug del negocio} {--dry-run : Solo analiza y reporta, no escribe}';

    protected $description = 'Importa conversaciones de WhatsApp (JSON) al panel: mensajes, direcciones deducidas y estimación de compras. No crea pedidos.';

    /** Zona horaria del export (los negocios en Perú lo exportan en hora local). */
    private const TZ = 'America/Lima';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path)) {
            $this->error("No existe el archivo: {$path}");

            return self::FAILURE;
        }

        $tenant = Tenant::where('slug', (string) $this->option('tenant'))->first();
        if (! $tenant) {
            $this->error("No existe el negocio '{$this->option('tenant')}'.");

            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($path), true);
        $chats = $data['data'] ?? null;
        if (! is_array($chats)) {
            $this->error('El JSON no tiene la estructura esperada (falta "data").');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $this->info(($dry ? '[DRY-RUN] ' : '') . "Importando " . count($chats) . " chats al negocio '{$tenant->name}'...");

        $stat = [
            'chats' => 0, 'msgs' => 0, 'msgs_dup' => 0,
            'clientes_nuevos' => 0, 'convs_nuevas' => 0,
            'direcciones' => 0, 'con_compra' => 0,
        ];

        foreach ($chats as $chat) {
            $contacto = trim((string) ($chat['contacto'] ?? ''));
            if ($contacto === '') {
                continue;
            }

            // Solo mensajes con texto y fecha (los "media/otro" y de sistema no aportan).
            $mensajes = array_values(array_filter(
                (array) ($chat['mensajes'] ?? []),
                fn ($m) => filled($m['texto'] ?? null) && filled($m['fecha'] ?? null),
            ));
            if ($mensajes === []) {
                continue;
            }

            $stat['chats']++;
            $customer = $this->resolveCustomer($tenant, $contacto, $dry, $stat);

            // Nombre visible del contacto (para la conversación).
            $contactName = $customer?->name
                ?: (str_starts_with($contacto, '@') ? $contacto : null);

            $conversation = $dry ? null : $this->resolveConversation($tenant, $customer, $contacto, $contactName);
            if (! $dry && $conversation) {
                $stat['convs_nuevas'] += $conversation->wasRecentlyCreated ? 1 : 0;
            }

            $lastAt = null;
            $lastInbound = null;

            foreach ($mensajes as $m) {
                $ts = $this->timestamp($m['fecha'], $m['hora'] ?? null);
                $role = ! empty($m['deMi']) ? 'assistant' : 'user';
                $lastAt = $lastAt && $lastAt->gt($ts) ? $lastAt : $ts;
                if ($role === 'user') {
                    $lastInbound = $lastInbound && $lastInbound->gt($ts) ? $lastInbound : $ts;
                }

                if ($dry) {
                    $stat['msgs']++;

                    continue;
                }

                $waId = 'imp_' . ($m['id'] ?? md5($contacto . $ts . ($m['texto'] ?? '')));
                if (Message::withoutGlobalScopes()->where('wa_message_id', $waId)->exists()) {
                    $stat['msgs_dup']++;

                    continue;
                }

                $msg = new Message([
                    'tenant_id' => $tenant->id,
                    'conversation_id' => $conversation->id,
                    'role' => $role,
                    'content' => (string) $m['texto'],
                    'wa_message_id' => $waId,
                ]);
                $msg->processed_at = $ts;   // histórico: el agente no lo reprocesa
                $msg->timestamps = false;
                $msg->created_at = $ts;
                $msg->updated_at = $ts;
                $msg->save();
                $stat['msgs']++;
            }

            if (! $dry && $conversation && $lastAt) {
                $conversation->forceFill([
                    'last_activity_at' => $lastAt,
                    'last_inbound_at' => $lastInbound,
                    'contact_name' => $conversation->contact_name ?: $contactName,
                ])->save();
            }

            // Dirección deducida del chat del cliente.
            if ($this->deduceAddress($tenant, $customer, $mensajes, $dry)) {
                $stat['direcciones']++;
            }

            // Estimación de compras (aproximada) anotada en el cliente.
            $compras = $this->estimatePurchases($mensajes);
            if ($compras > 0) {
                $stat['con_compra']++;
                if (! $dry && $customer) {
                    $this->annotatePurchases($customer, $compras);
                }
            }
        }

        $this->table(['Métrica', 'Valor'], [
            ['Chats procesados', $stat['chats']],
            ['Mensajes importados', $stat['msgs']],
            ['Mensajes ya existentes (omitidos)', $stat['msgs_dup']],
            ['Clientes nuevos creados', $stat['clientes_nuevos']],
            ['Conversaciones nuevas', $stat['convs_nuevas']],
            ['Direcciones deducidas', $stat['direcciones']],
            ['Clientes con compra estimada', $stat['con_compra']],
        ]);

        $this->info($dry ? 'DRY-RUN terminado (no se escribió nada).' : 'Importación terminada.');

        return self::SUCCESS;
    }

    /** Encuentra o crea el cliente del contacto (teléfono, @username o nombre). */
    private function resolveCustomer(Tenant $tenant, string $contacto, bool $dry, array &$stat): ?Customer
    {
        $q = Customer::withoutGlobalScopes()->where('tenant_id', $tenant->id);

        [$attrs, $lookup] = $this->contactAttributes($contacto);
        $customer = (clone $q)->where($lookup)->first();

        if ($customer) {
            return $customer;
        }
        if ($dry) {
            $stat['clientes_nuevos']++;

            return null;
        }

        $customer = Customer::withoutGlobalScopes()->create(array_merge(
            ['tenant_id' => $tenant->id, 'notes' => 'Importado de WhatsApp'],
            $attrs,
        ));
        $stat['clientes_nuevos']++;

        return $customer;
    }

    /**
     * Atributos de creación y criterio de búsqueda según el tipo de contacto.
     *
     * @return array{0: array<string,string>, 1: array<string,string>}
     */
    private function contactAttributes(string $contacto): array
    {
        if (str_starts_with($contacto, '+')) {
            $phone = '+' . preg_replace('/\D/', '', $contacto);

            return [['phone' => $phone], ['phone' => $phone]];
        }
        if (str_starts_with($contacto, '@')) {
            $username = ltrim($contacto, '@');

            return [['username' => $username], ['username' => $username]];
        }

        return [['name' => $contacto], ['name' => $contacto]];
    }

    /** Reutiliza la conversación del cliente/contacto o crea una nueva. */
    private function resolveConversation(Tenant $tenant, ?Customer $customer, string $contacto, ?string $name): Conversation
    {
        $q = Conversation::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tenant->id);

        if ($customer) {
            $q->where('customer_id', $customer->id);
        } elseif (str_starts_with($contacto, '+')) {
            $q->where('phone', '+' . preg_replace('/\D/', '', $contacto));
        } else {
            $q->where('contact_name', $contacto);
        }

        $conversation = $q->first();
        if ($conversation) {
            if ($conversation->trashed()) {
                $conversation->restore();
            }
            $conversation->wasRecentlyCreated = false;

            return $conversation;
        }

        return Conversation::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer?->id,
            'phone' => str_starts_with($contacto, '+') ? '+' . preg_replace('/\D/', '', $contacto) : null,
            'contact_name' => $name,
            'channel' => 'whatsapp',
            'status' => 'bot',
        ]);
    }

    /** fecha "2026-09-04" + hora "15:01" (hora de Perú) -> Carbon en UTC. */
    private function timestamp(string $fecha, ?string $hora): Carbon
    {
        $hora = ($hora && preg_match('/^\d{1,2}:\d{2}$/', $hora)) ? $hora : '12:00';

        return Carbon::parse("{$fecha} {$hora}", self::TZ)->utc();
    }

    /**
     * Deduce y guarda la dirección del cliente a partir de sus mensajes. Elige la
     * línea con más señales de dirección (calle + número + referencia) y agrega
     * las referencias sueltas. Solo guarda si el cliente aún no tiene dirección.
     */
    private function deduceAddress(Tenant $tenant, ?Customer $customer, array $mensajes, bool $dry): bool
    {
        $mejor = null;
        $mejorScore = 0;
        $referencias = [];

        foreach ($mensajes as $m) {
            if (! empty($m['deMi'])) {
                continue; // solo lo que escribió el cliente
            }
            $texto = trim((string) $m['texto']);
            if ($texto === '' || mb_strlen($texto) > 160) {
                continue; // descartamos párrafos largos (no son direcciones)
            }
            // Para ser dirección exigimos un tipo de vía (jr/av/calle/mz...): así
            // "entre 3 o 4 pm" (un horario) no se confunde con una dirección.
            $score = $this->hasStreet($texto) ? $this->addressScore($texto) : 0;
            if ($score >= 2 && $score > $mejorScore) {
                if ($mejor) {
                    $referencias[] = $mejor;
                }
                $mejor = $texto;
                $mejorScore = $score;
            } elseif ($score >= 1 && $this->isReference($texto) && count($referencias) < 3) {
                $referencias[] = $texto;
            }
        }

        if ($mejor === null) {
            return false;
        }
        if ($dry || ! $customer) {
            return true;
        }

        // No duplicar: si ya tiene una dirección importada/registrada, no tocar.
        if ($customer->addresses()->exists()) {
            return true;
        }

        $ref = collect($referencias)->unique()->reject(fn ($r) => $r === $mejor)->take(2)->implode(' · ');

        Address::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'address' => $mejor,
            'reference' => $ref !== '' ? $ref . ' (deducido del chat)' : 'Deducido del chat de WhatsApp',
            'is_primary' => true,
        ]);

        return true;
    }

    /** ¿El texto nombra un tipo de vía (jr/av/calle/mz/urb...)? */
    private function hasStreet(string $t): bool
    {
        return (bool) preg_match('/\b(jr\.?|jir[oó]n|av\.?|avenida|calle|ca\.?|pasaje|psje|pje\.?|prolongaci[oó]n|prol\.?|mz\.?|manzana|urb\.?|block)\b/iu', $t);
    }

    /** Puntúa qué tan "dirección" es un texto: calle(2) + número(1) + referencia(1). */
    private function addressScore(string $t): int
    {
        $score = 0;
        if ($this->hasStreet($t)) {
            $score += 2;
        }
        if (preg_match('/\b\d{1,4}[a-z]?\b/iu', $t)) {
            $score += 1;
        }
        if ($this->isReference($t)) {
            $score += 1;
        }

        return $score;
    }

    private function isReference(string $t): bool
    {
        return (bool) preg_match('/\b(frente|costado|espalda|altura|cuadra|cuadras|referencia|cerca|al lado|esquina|colegio|mercado|parque|plaza|iglesia|grifo|posta|entre)\b/iu', $t);
    }

    /**
     * Estima cuántos pedidos hizo el cliente: cuenta días distintos con señal de
     * entrega o pago (aproximado, para dar una idea; no crea pedidos).
     */
    private function estimatePurchases(array $mensajes): int
    {
        $dias = [];
        foreach ($mensajes as $m) {
            $t = (string) $m['texto'];
            $entrega = preg_match('/\b(en camino|ya sali[oó]|llegando|est[aá] llegando|en la puerta|lleg[oó]|entregad|ya lo llev|ya sale|ah[ií] va|va en camino|listo su pedido|en la puerta)\b/iu', $t);
            $pago = preg_match('/\b(yape|yapeo|yapea|yapead|plin|transferenc|dep[oó]sito|pagado|pagu[eé]|cancel[oó]|cancelad)\b/iu', $t);
            if ($entrega || $pago) {
                $dias[substr((string) $m['fecha'], 0, 10)] = true;
            }
        }

        return count($dias);
    }

    /** Anota la estimación de compras en las notas del cliente (sin duplicar). */
    private function annotatePurchases(Customer $customer, int $compras): void
    {
        $nota = "≈{$compras} pedido" . ($compras === 1 ? '' : 's') . ' (estimado del historial WhatsApp)';
        $actual = (string) $customer->notes;
        if (str_contains($actual, 'estimado del historial WhatsApp')) {
            $actual = trim((string) preg_replace('/≈\d+ pedidos?.*?WhatsApp\)/u', '', $actual), " \n·");
        }
        $customer->forceFill([
            'notes' => trim($actual === '' ? $nota : "{$actual} · {$nota}", ' ·'),
        ])->save();
    }
}
