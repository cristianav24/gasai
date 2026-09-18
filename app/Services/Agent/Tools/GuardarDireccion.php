<?php

namespace App\Services\Agent\Tools;

use App\Models\Address;
use App\Services\Agent\AgentContext;
use App\Services\Geo\Geocoder;

/**
 * Guarda una dirección de entrega para el cliente de ESTA conversación.
 * Si no le pasan coordenadas, las resuelve solo (geocodificación) para que el
 * cobro por distancia funcione sin depender de que el modelo copie lat/lng.
 */
class GuardarDireccion implements Tool
{
    public function __construct(private Geocoder $geocoder) {}

    public function name(): string
    {
        return 'guardar_direccion';
    }

    public function description(): string
    {
        return 'Guarda la dirección de entrega del cliente. Pásale la dirección y el DISTRITO; '
            . 'el sistema le pone la ubicación (coordenadas) automáticamente para calcular el envío. '
            . 'No recibe cliente: siempre es el cliente de esta conversación.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'direccion' => ['type' => 'string', 'description' => 'Dirección (calle y número).'],
                'distrito' => ['type' => 'string', 'description' => 'Distrito/zona (ej. El Tambo, Chilca). Muy recomendado: mejora la ubicación.'],
                'referencia' => ['type' => 'string', 'description' => 'Referencia de ubicación (opcional).'],
                'lat' => ['type' => 'number', 'description' => 'Latitud, solo si el cliente compartió su ubicación por WhatsApp.'],
                'lng' => ['type' => 'number', 'description' => 'Longitud, solo si el cliente compartió su ubicación por WhatsApp.'],
                'principal' => ['type' => 'boolean', 'description' => 'Marcar como dirección principal.'],
            ],
            'required' => ['direccion'],
        ];
    }

    public function handle(array $arguments, AgentContext $context): array
    {
        $direccion = trim((string) ($arguments['direccion'] ?? ''));
        if ($direccion === '') {
            return ['ok' => false, 'error' => 'Falta la dirección.'];
        }

        $lat = isset($arguments['lat']) ? (float) $arguments['lat'] : null;
        $lng = isset($arguments['lng']) ? (float) $arguments['lng'] : null;

        // Si no vinieron coordenadas, las resolvemos solos (no dependemos del modelo).
        if ($lat === null || $lng === null) {
            $t = $context->tenant;
            $distrito = isset($arguments['distrito']) ? (string) $arguments['distrito'] : null;
            $opciones = $this->geocoder->search($direccion, $distrito, $t->geo_city, $t->geo_region, $t->geo_country, $t->geo_viewbox);
            if (! empty($opciones) && $opciones[0]['lat'] !== null) {
                $lat = (float) $opciones[0]['lat'];
                $lng = (float) $opciones[0]['lng'];
            }
        }

        $customer = $context->ensureCustomer();
        $esPrincipal = (bool) ($arguments['principal'] ?? false);

        if ($esPrincipal) {
            $customer->addresses()->update(['is_primary' => false]);
        }

        $address = Address::create([
            'tenant_id' => $context->tenantId(),
            'customer_id' => $customer->id,
            'address' => $direccion,
            'reference' => isset($arguments['referencia']) ? trim((string) $arguments['referencia']) : null,
            'lat' => $lat,
            'lng' => $lng,
            'is_primary' => $esPrincipal,
        ]);

        return [
            'ok' => true,
            'direccion_id' => $address->id,
            'direccion' => $address->address,
            'referencia' => $address->reference,
            'con_ubicacion' => $lat !== null,
            'principal' => $address->is_primary,
        ];
    }
}
