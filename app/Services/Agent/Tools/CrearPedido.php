<?php

namespace App\Services\Agent\Tools;

use App\Models\Branch;
use App\Models\Order;
use App\Services\Agent\AgentContext;
use App\Services\Agent\OrderPricing;
use Illuminate\Support\Facades\DB;

/**
 * Crea un pedido con sus líneas. Reglas duras:
 * - Exige fecha Y franja horaria; nunca guarda un pedido "a las 00:00".
 * - Los precios se congelan desde la BD (OrderPricing), no del LLM.
 * - El tenant y la sucursal salen del servidor.
 */
class CrearPedido implements Tool
{
    /** Franjas válidas. */
    private const SLOTS = ['manana', 'tarde', 'hora_exacta'];

    public function __construct(private OrderPricing $pricing) {}

    public function name(): string
    {
        return 'crear_pedido';
    }

    public function description(): string
    {
        return 'Crea el pedido del cliente. Requiere cliente, dirección, items, y OBLIGATORIAMENTE una '
            . 'fecha programada y una franja horaria (manana, tarde u hora_exacta). Si es hora_exacta, '
            . 'incluye la hora. No inventes disponibilidad ni tiempos de entrega.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cliente_id' => ['type' => 'integer'],
                'direccion_id' => ['type' => 'integer'],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'producto_id' => ['type' => 'integer'],
                            'cantidad' => ['type' => 'integer'],
                        ],
                        'required' => ['producto_id', 'cantidad'],
                    ],
                ],
                'zona_id' => ['type' => 'integer', 'description' => 'Zona de entrega (opcional).'],
                'fecha_programada' => [
                    'type' => 'string',
                    'description' => 'Fecha de entrega en formato YYYY-MM-DD.',
                ],
                'franja' => [
                    'type' => 'string',
                    'enum' => self::SLOTS,
                    'description' => 'Franja horaria: manana, tarde u hora_exacta.',
                ],
                'hora' => [
                    'type' => 'string',
                    'description' => 'Hora HH:MM, obligatoria solo si la franja es hora_exacta.',
                ],
                'notas' => ['type' => 'string'],
            ],
            'required' => ['cliente_id', 'items', 'fecha_programada', 'franja'],
        ];
    }

    public function handle(array $arguments, AgentContext $context): array
    {
        // --- Validación de agenda (regla dura) ---
        $fecha = trim((string) ($arguments['fecha_programada'] ?? ''));
        $franja = (string) ($arguments['franja'] ?? '');

        if ($fecha === '') {
            return ['ok' => false, 'error' => 'Falta la fecha programada. Pregúntale al cliente para qué día quiere la entrega.'];
        }

        if (! in_array($franja, self::SLOTS, true)) {
            return ['ok' => false, 'error' => 'Falta la franja horaria. Pregúntale si prefiere mañana, tarde o una hora exacta.'];
        }

        $hora = null;
        if ($franja === 'hora_exacta') {
            $hora = trim((string) ($arguments['hora'] ?? ''));
            if ($hora === '') {
                return ['ok' => false, 'error' => 'La franja es hora exacta pero falta la hora. Pregúntale la hora al cliente.'];
            }
        }

        // --- Cálculo de precios desde el código ---
        $items = $arguments['items'] ?? [];
        $zonaId = isset($arguments['zona_id']) ? (int) $arguments['zona_id'] : null;

        $calc = $this->pricing->calcular($items, $zonaId);

        if (! empty($calc['errores'])) {
            return ['ok' => false, 'error' => implode(' ', $calc['errores'])];
        }

        if (empty($calc['lineas'])) {
            return ['ok' => false, 'error' => 'El pedido no tiene items válidos.'];
        }

        // Sucursal por defecto del tenant (capa distinta del multi-tenant).
        $branch = Branch::where('active', true)->orderBy('id')->first()
            ?? Branch::orderBy('id')->first();

        if (! $branch) {
            return ['ok' => false, 'error' => 'El negocio no tiene una sucursal configurada.'];
        }

        $order = DB::transaction(function () use ($arguments, $context, $calc, $fecha, $franja, $hora, $zonaId, $branch): Order {
            $order = Order::create([
                'tenant_id' => $context->tenantId(),
                'branch_id' => $branch->id,
                'customer_id' => $arguments['cliente_id'] ?? null,
                'address_id' => $arguments['direccion_id'] ?? null,
                'delivery_zone_id' => $zonaId,
                'subtotal' => $calc['subtotal'],
                'delivery_fee' => $calc['costo_envio'],
                'total' => $calc['total'],
                'status' => 'pendiente',
                'channel' => $context->conversation->channel,
                'scheduled_date' => $fecha,
                'scheduled_slot' => $franja,
                'scheduled_time' => $hora,
                'notes' => $arguments['notas'] ?? null,
            ]);

            foreach ($calc['lineas'] as $linea) {
                // Congelamos nombre y precio de lista en la línea.
                $order->items()->create([
                    'tenant_id' => $context->tenantId(),
                    'product_id' => $linea['producto_id'],
                    'product_name' => $linea['nombre'],
                    'quantity' => $linea['cantidad'],
                    'unit_price_list' => $linea['precio_unitario'],
                ]);
            }

            return $order;
        });

        return [
            'ok' => true,
            'pedido_id' => $order->id,
            'estado' => $order->status,
            'total' => (float) $order->total,
            'fecha_programada' => $fecha,
            'franja' => $franja,
            'hora' => $hora,
        ];
    }
}
