<?php

namespace App\Services\Delivery;

use App\Models\Tenant;

/**
 * Cotiza el costo de envío por distancia desde el punto central del negocio,
 * usando bandas configurables. La primera banda (fee 0) es el radio gratis;
 * más allá de la última banda, la dirección queda fuera de cobertura.
 *
 * La distancia es en línea recta (Haversine) con un factor para aproximarla a
 * distancia por calle (sin depender de una API de rutas de pago).
 */
class DeliveryPricing
{
    /** Factor para acercar la distancia recta a la distancia por calle. */
    private const ROAD_FACTOR = 1.3;

    /**
     * @return array{metodo: string, fee: ?float, distancia_km: ?float, cubierto: bool, gratis_por_monto: bool}
     *   metodo: 'distancia' cuando se cobró por distancia, 'sin_ubicacion' cuando
     *   no se pudo (sin coordenadas o sin config) y el llamador debe usar la zona.
     */
    public function quote(Tenant $tenant, ?float $lat, ?float $lng, float $subtotal): array
    {
        $freeOver = $tenant->delivery_free_over !== null ? (float) $tenant->delivery_free_over : null;
        $dist = $this->distance($tenant, $lat, $lng);

        // Envío gratis por monto mínimo.
        if ($freeOver !== null && $freeOver > 0 && $subtotal >= $freeOver) {
            return ['metodo' => 'distancia', 'fee' => 0.0, 'distancia_km' => $dist, 'cubierto' => true, 'gratis_por_monto' => true];
        }

        $bands = $this->bands($tenant);

        // Sin bandas, sin centro o sin coordenadas del cliente → que el llamador use la zona.
        if (empty($bands) || $dist === null) {
            return ['metodo' => 'sin_ubicacion', 'fee' => null, 'distancia_km' => $dist, 'cubierto' => true, 'gratis_por_monto' => false];
        }

        foreach ($bands as $b) {
            if ($dist <= $b['to_km'] + 1e-9) {
                return ['metodo' => 'distancia', 'fee' => $b['fee'], 'distancia_km' => $dist, 'cubierto' => true, 'gratis_por_monto' => false];
            }
        }

        // Más allá de la última banda: fuera de cobertura.
        return ['metodo' => 'distancia', 'fee' => null, 'distancia_km' => $dist, 'cubierto' => false, 'gratis_por_monto' => false];
    }

    /** @return array<int, array{to_km: float, fee: float}> ordenadas por distancia. */
    private function bands(Tenant $tenant): array
    {
        $bands = [];
        foreach ((array) ($tenant->delivery_bands ?? []) as $b) {
            if (! isset($b['to_km'])) {
                continue;
            }
            $bands[] = ['to_km' => (float) $b['to_km'], 'fee' => round((float) ($b['fee'] ?? 0), 2)];
        }
        usort($bands, fn ($a, $c): int => $a['to_km'] <=> $c['to_km']);

        return $bands;
    }

    /** Distancia estimada por calle (km) del centro a un punto, o null si falta info. */
    public function distance(Tenant $tenant, ?float $lat, ?float $lng): ?float
    {
        if ($tenant->delivery_center_lat === null || $tenant->delivery_center_lng === null || $lat === null || $lng === null) {
            return null;
        }

        $km = $this->haversine(
            (float) $tenant->delivery_center_lat,
            (float) $tenant->delivery_center_lng,
            $lat,
            $lng,
        );

        return round($km * self::ROAD_FACTOR, 2);
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0; // radio de la Tierra en km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
