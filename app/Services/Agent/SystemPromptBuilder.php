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
        // Importante: en el job de WhatsApp no hay tenant en el scope global, así
        // que acotamos SIEMPRE por el negocio de la conversación (si no, leeríamos
        // la config/productos de otro negocio).
        $config = BotConfig::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

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
        - El costo de envío es INTERNO: nunca le muestres al cliente una línea de "envío" aparte ni menciones la distancia. En el resumen y la confirmación del pedido, muestra el precio de cada producto CON EL ENVÍO YA INCLUIDO (usa "precio_con_envio" que devuelven calcular_total y crear_pedido), de modo que precio × cantidad sume exactamente el total. NUNCA muestres un precio base que después no cuadre con el total (nada de "S/ 10" y luego "Total S/ 13").
        - Vende ÚNICAMENTE los productos del catálogo de abajo. NUNCA ofrezcas, menciones ni inventes productos que no estén en ese catálogo (por ejemplo, balones de gas si no aparecen). Si el cliente pide algo que no está en el catálogo, dile con amabilidad que no lo manejas y ofrécele lo que sí vendes.
        - Nunca inventes disponibilidad, cobertura ni tiempos de entrega que no estén en la información dada.
        - Para agendar necesitas fecha Y franja horaria (mañana, tarde u hora exacta). No cierres un pedido sin ambas.
        - Al confirmar un pedido, muéstrale al cliente el NÚMERO DE PEDIDO tal como lo devuelve crear_pedido en "numero_pedido" (ej. "Pedido #00001559"). Nunca uses el id interno.
        - Respeta el horario de atención (ver abajo). Si el cliente pide "ahora"/"hoy" pero ya estás fuera del horario, NO agendes para hoy: dile con amabilidad que ya cerraron y ofrécele el siguiente horario disponible (por ejemplo mañana en la mañana).
        - Si el cliente comparte su ubicación (verás "📍 Ubicación compartida: lat,lng" y una "Dirección aproximada (por GPS)"), agradécele y usa esas coordenadas al guardar su dirección con guardar_direccion. La dirección del GPS es más confiable que la zona que el cliente escriba de memoria: si no coinciden, guíate por la del GPS y coteja la zona con esa (pídele una referencia del lugar de todas formas).
        - Cuando el cliente te escriba su dirección en TEXTO (no por GPS), pídele SIEMPRE el distrito si no lo mencionó (ej. El Tambo, Chilca, Huancayo): eso la vuelve mucho más exacta. Luego guárdala DIRECTO con guardar_direccion (dirección + distrito): el sistema le pone la ubicación (coordenadas) automáticamente, tú NO necesitas copiar lat/lng ni llamar validar_direccion para guardar. Solo si el cliente comparte su ubicación por WhatsApp, pásale ese lat/lng a guardar_direccion. Ofrécele compartir su ubicación si quieres más exactitud, pero no la exijas.
        - Si el cliente envía una imagen, audio o documento, no puedes verlos: dile con amabilidad que por ahora solo entiendes texto y pídele que te escriba lo que necesita.
        - Si no puedes resolver algo o el cliente lo pide, usa escalar_a_humano.
        - Responde en español, breve y claro, como en un chat de WhatsApp. Escribe COMPACTO: NO dejes líneas en blanco entre frases (nada de dobles saltos de línea). Usa un salto de línea solo cuando de verdad ayuda, como una lista corta. Evita mensajes largos y espaciados.
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
        // Si el negocio cobra envío por distancia, el precio final depende de la
        // dirección (el envío va prorrateado DENTRO del precio). En ese caso NO
        // mostramos el precio base en el catálogo: si el bot lo cotizara antes de
        // tener la dirección, luego no cuadraría con el total (S/10 -> S/11) y el
        // cliente reclamaría. El precio lo da calcular_total, ya con el envío.
        $reparto_por_distancia = filled($tenant->delivery_bands) && $tenant->delivery_center_lat !== null;
        $productos = Product::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->where('active', true)->orderBy('name')->get();
        if ($productos->isNotEmpty()) {
            $lineas = $productos->map(function (Product $p) use ($reparto_por_distancia): string {
                $tipo = $p->type === 'recarga' ? 'recarga' : 'venta';
                if ($reparto_por_distancia) {
                    return "- [#{$p->id}] {$p->name} (por {$p->unit}, {$tipo})";
                }
                $precio = number_format((float) $p->price, 2);
                return "- [#{$p->id}] {$p->name}: S/ {$precio} por {$p->unit} ({$tipo})";
            })->implode("\n");
            $partes[] = $reparto_por_distancia
                ? "Catálogo de productos (usa estos IDs). El PRECIO no está aquí a propósito: lo da calcular_total/crear_pedido con la dirección (ya incluye el envío). NUNCA cotices un precio de memoria:\n{$lineas}"
                : "Catálogo de productos (usa estos IDs y precios):\n{$lineas}";
        } else {
            $partes[] = 'El negocio todavía no cargó productos.';
        }

        // --- Zonas de entrega ---
        $zonas = DeliveryZone::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->where('active', true)->orderBy('name')->get();
        if ($zonas->isNotEmpty()) {
            $lineas = $zonas->map(function (DeliveryZone $z): string {
                $costo = number_format((float) $z->delivery_fee, 2);
                $cobertura = filled($z->coverage) ? " — {$z->coverage}" : '';
                return "- [#{$z->id}] {$z->name}: envío S/ {$costo}{$cobertura}";
            })->implode("\n");
            $partes[] = "Zonas de entrega:\n{$lineas}";
        }

        // --- Cobro de envío por distancia (mecánica INTERNA; el cliente solo ve el total) ---
        if ($reparto_por_distancia) {
            $partes[] = 'Reparto (interno, no se lo expliques al cliente): el sistema calcula el envío automáticamente por la distancia '
                . 'desde el local hasta la dirección, y lo suma DENTRO del precio. Por eso NUNCA le des al cliente un precio ni un total '
                . 'antes de tener su dirección guardada con ubicación: si lo haces, el número cambiará al armar el pedido y el cliente '
                . 'reclamará. Si te pregunta el precio antes de darte su dirección, NO sueltes una cifra: dile con amabilidad que le das el '
                . 'precio exacto (con la entrega ya incluida) apenas te pase su dirección, y pídesela. Flujo correcto: primero guarda la '
                . 'dirección con su ubicación (guardar_direccion con dirección + distrito, o la ubicación por WhatsApp), y RECIÉN entonces '
                . 'llama a calcular_total / crear_pedido con la direccion_id y cotiza el precio_con_envio que devuelven. El cliente solo ve '
                . 'ese total final. Si el sistema indica que la dirección está fuera del área de cobertura, dile con amabilidad que por ahora '
                . 'no llegamos hasta esa zona (sin hablar de kilómetros) y no agendes.';
        }

        // --- Base de conocimiento (respetando el límite de tamaño) ---
        $conocimiento = $this->knowledgeBlock($tenant);
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
    private function knowledgeBlock(\App\Models\Tenant $tenant): string
    {
        $items = KnowledgeItem::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->where('active', true)->orderBy('id')->get();

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
