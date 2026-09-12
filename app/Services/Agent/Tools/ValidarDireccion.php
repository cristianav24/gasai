<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentContext;
use App\Services\Geo\Geocoder;

/**
 * Geocodificación directa: valida y ubica en el mapa la dirección que el cliente
 * escribió en texto, acotada a la zona del negocio y filtrada por distrito.
 */
class ValidarDireccion implements Tool
{
    public function __construct(private Geocoder $geocoder) {}

    public function name(): string
    {
        return 'validar_direccion';
    }

    public function description(): string
    {
        return 'Valida y ubica una dirección que el cliente escribió en texto, dentro de la zona del negocio. '
            . 'Pásale la dirección y el DISTRITO (pídeselo al cliente si no lo mencionó: hace la ubicación mucho más exacta). '
            . 'Devuelve candidatos con su ubicación (lat/lng); si viene vacío, NO la fuerces: pídele que comparta su '
            . 'ubicación por WhatsApp o una referencia clara. Nunca inventes coordenadas.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'direccion' => ['type' => 'string', 'description' => 'Dirección tal como la escribió el cliente (calle y número).'],
                'distrito' => ['type' => 'string', 'description' => 'Distrito del cliente (ej. El Tambo, Chilca, Huancayo). Muy recomendado.'],
            ],
            'required' => ['direccion'],
        ];
    }

    public function handle(array $arguments, AgentContext $context): array
    {
        $tenant = $context->tenant;

        $opciones = $this->geocoder->search(
            (string) ($arguments['direccion'] ?? ''),
            isset($arguments['distrito']) ? (string) $arguments['distrito'] : null,
            $tenant->geo_city,
            $tenant->geo_region,
            $tenant->geo_country,
            $tenant->geo_viewbox,
        );

        return [
            'encontrado' => ! empty($opciones),
            'opciones' => $opciones,
        ];
    }
}
