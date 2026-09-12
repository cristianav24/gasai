<?php

namespace App\Services\Agent;

use App\Models\BotConfig;
use App\Models\DeliveryZone;
use App\Models\KnowledgeItem;
use App\Models\Product;
use Illuminate\Support\Carbon;

/**
 * Arma el system prompt del agente: plantilla base + config del bot +
 * conocimiento activo + catálogo con precios + zonas + contexto del cliente.
 */
class SystemPromptBuilder
{
    public function build(AgentContext $context): string
    {
        $tenant = $context->tenant;
        $config = BotConfig::first();

        $agentName = $config?->agent_name ?? 'Asistente';
        $tone = $config?->tone ?? 'amable';

        $partes = [];

        // --- Identidad y reglas base ---
        $partes[] = <<<TXT
        Eres {$agentName}, el asistente de pedidos por WhatsApp de "{$tenant->name}".
        Tu trabajo es atender al cliente con un tono {$tone}, resolver sus dudas con la información
        de la empresa, armar su pedido y capturar los datos de entrega.

        Reglas que debes cumplir siempre:
        - Preséntate por tu nombre ({$agentName}) la primera vez que saludas al cliente.
        - El sistema ya sabe quién te escribe: NUNCA pidas ni inventes su número de teléfono para identificarlo. Usa buscar_cliente (sin datos) para ver si ya lo conocemos; si ya pidió antes, no le pidas todos los datos de nuevo.
        - Si es un cliente nuevo y te da su nombre, regístralo con guardar_cliente (solo el nombre). Todas las herramientas (direcciones, pedido, envases) actúan sobre el cliente de ESTA conversación; no llevan cliente_id.
        - Usa SIEMPRE los precios y totales que devuelven las herramientas. Nunca inventes ni calcules precios tú.
        - Nunca inventes disponibilidad, cobertura ni tiempos de entrega que no estén en la información dada.
        - Para agendar necesitas fecha Y franja horaria (mañana, tarde u hora exacta). No cierres un pedido sin ambas.
        - Respeta el horario de atención (ver abajo). Si el cliente pide "ahora"/"hoy" pero ya estás fuera del horario, NO agendes para hoy: dile con amabilidad que ya cerraron y ofrécele el siguiente horario disponible (por ejemplo mañana en la mañana).
        - Si el cliente comparte su ubicación (verás "📍 Ubicación compartida: lat,lng" y una "Dirección aproximada (por GPS)"), agradécele y usa esas coordenadas al guardar su dirección con guardar_direccion. La dirección del GPS es más confiable que la zona que el cliente escriba de memoria: si no coinciden, guíate por la del GPS y coteja la zona con esa (pídele una referencia del lugar de todas formas).
        - Si el cliente envía una imagen, audio o documento, no puedes verlos: dile con amabilidad que por ahora solo entiendes texto y pídele que te escriba lo que necesita.
        - Si no puedes resolver algo o el cliente lo pide, usa escalar_a_humano.
        - Responde en español, breve y claro, como en un chat de WhatsApp.
        TXT;

        // --- Mensaje de bienvenida configurado por el dueño ---
        if ($config && filled($config->welcome_message)) {
            $partes[] = "Al saludar por primera vez, usa este mensaje de bienvenida (adáptalo al cliente si ya lo conoces):\n" . trim($config->welcome_message);
        }

        // --- Contexto temporal (para resolver "hoy", "mañana", "ahora") ---
        $tz = $tenant->timezone ?: 'America/Lima';
        $ahora = Carbon::now($tz)->locale('es');
        $hoy = $ahora->format('Y-m-d');
        $manana = $ahora->copy()->addDay()->format('Y-m-d');
        $partes[] = <<<TXT
        Contexto de tiempo (zona horaria {$tz}):
        - Hoy es {$ahora->isoFormat('dddd D [de] MMMM [de] YYYY')} ({$hoy}). Hora actual: {$ahora->format('H:i')}.
        - Resuelve tú mismo las fechas relativas: "hoy" = {$hoy}; "mañana" = {$manana}. Nunca le pidas al cliente que te confirme la fecha de hoy: ya la sabes.
        - Si el cliente dice "ahora", "ya" o "lo antes posible", usa la fecha de hoy ({$hoy}) y la franja "hora_exacta" con la hora actual ({$ahora->format('H:i')}). No le vuelvas a preguntar la fecha.
        - Al llamar a crear_pedido, envía la fecha en formato YYYY-MM-DD.
        TXT;

        // --- Horario de atención del negocio ---
        $horario = $this->horarioAtencion($tenant);
        if ($horario !== '') {
            $partes[] = "Horario de atención: {$horario}.\n"
                . "Compara la hora actual ({$ahora->format('H:i')} del " . $ahora->isoFormat('dddd') . ") con este horario. "
                . "Si el negocio ya cerró o aún no abre, NO agendes una entrega para 'ahora' ni para hoy fuera de hora: "
                . "avísale al cliente que están cerrados y ofrécele el próximo horario disponible (el siguiente día/turno de atención).";
        }

        // --- Instrucciones extra del dueño ---
        if ($config && filled($config->extra_instructions)) {
            $partes[] = "Instrucciones adicionales del negocio:\n" . trim($config->extra_instructions);
        }

        // --- Catálogo con precios reales ---
        $productos = Product::where('active', true)->orderBy('name')->get();
        if ($productos->isNotEmpty()) {
            $lineas = $productos->map(function (Product $p): string {
                $precio = number_format((float) $p->price, 2);
                $tipo = $p->type === 'recarga' ? 'recarga' : 'venta';
                return "- [#{$p->id}] {$p->name}: S/ {$precio} por {$p->unit} ({$tipo})";
            })->implode("\n");
            $partes[] = "Catálogo de productos (usa estos IDs y precios):\n{$lineas}";
        } else {
            $partes[] = 'El negocio todavía no cargó productos.';
        }

        // --- Zonas de entrega ---
        $zonas = DeliveryZone::where('active', true)->orderBy('name')->get();
        if ($zonas->isNotEmpty()) {
            $lineas = $zonas->map(function (DeliveryZone $z): string {
                $costo = number_format((float) $z->delivery_fee, 2);
                $cobertura = filled($z->coverage) ? " — {$z->coverage}" : '';
                return "- [#{$z->id}] {$z->name}: envío S/ {$costo}{$cobertura}";
            })->implode("\n");
            $partes[] = "Zonas de entrega:\n{$lineas}";
        }

        // --- Base de conocimiento (respetando el límite de tamaño) ---
        $conocimiento = $this->knowledgeBlock();
        if ($conocimiento !== '') {
            $partes[] = "Información de la empresa:\n{$conocimiento}";
        }

        // --- Contexto del cliente si ya se identificó ---
        if ($context->customer) {
            $c = $context->customer;
            $nombre = $c->name ?? '(sin nombre)';
            $tel = $c->phone ? ", teléfono {$c->phone}" : '';
            $partes[] = "Cliente identificado: {$nombre}{$tel}. (Las herramientas ya actúan sobre este cliente; no necesitas su id ni su número.)";
        }

        return implode("\n\n", $partes);
    }

    /** Texto del horario de atención del tenant (guardado como array u string). */
    private function horarioAtencion(\App\Models\Tenant $tenant): string
    {
        $bh = $tenant->business_hours;

        if (is_array($bh)) {
            return trim((string) ($bh['texto'] ?? ''));
        }

        return trim((string) $bh);
    }

    /**
     * Une el conocimiento activo respetando MAX_TOTAL_CHARS. Si se pasa, corta y
     * avisa (no revienta el prompt ni encarece la llamada).
     */
    private function knowledgeBlock(): string
    {
        $items = KnowledgeItem::where('active', true)->orderBy('id')->get();

        $buffer = '';
        $limite = KnowledgeItem::MAX_TOTAL_CHARS;

        foreach ($items as $item) {
            $bloque = "• {$item->title}: {$item->content}\n";

            if (mb_strlen($buffer) + mb_strlen($bloque) > $limite) {
                $buffer .= "(Se omitió parte del conocimiento por límite de tamaño.)\n";
                break;
            }

            $buffer .= $bloque;
        }

        return trim($buffer);
    }
}
