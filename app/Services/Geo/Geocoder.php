<?php

namespace App\Services\Geo;

use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

/**
 * Geocodificación con Nominatim (OpenStreetMap), gratis y sin API key. Su
 * política pide un User-Agent identificable y un uso moderado. Dos usos:
 * - reverse(): coordenadas -> dirección legible (para ubicaciones por GPS).
 * - search():  texto -> ubicación exacta, acotada a la zona del negocio.
 */
class Geocoder
{
    public function __construct(
        private HttpFactory $http,
        private string $baseUrl = 'https://nominatim.openstreetmap.org',
    ) {}

    private function client()
    {
        return $this->http
            ->withHeaders(['User-Agent' => 'GasAI/1.0 (soporte@tandix.app)'])
            ->timeout(6);
    }

    /** Dirección aproximada de unas coordenadas, o null si falla. */
    public function reverse(float $lat, float $lng): ?string
    {
        try {
            $response = $this->client()->get("{$this->baseUrl}/reverse", [
                'format' => 'jsonv2',
                'lat' => $lat,
                'lon' => $lng,
                'accept-language' => 'es',
                'zoom' => 18,
            ]);

            if ($response->failed()) {
                return null;
            }

            $address = $response->json('display_name');

            return is_string($address) && $address !== '' ? $address : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Geocodificación directa: convierte la dirección escrita por el cliente en
     * candidatos con su ubicación exacta, acotada a la zona del negocio y
     * filtrada por distrito. Vacío = no existe en OSM (no forzar).
     *
     * @return array<int, array{direccion: string, lat: ?float, lng: ?float, distrito: ?string}>
     */
    public function search(
        string $direccion,
        ?string $distrito = null,
        ?string $city = null,
        ?string $region = null,
        ?string $country = null,
        ?string $viewbox = null,
    ): array {
        $direccion = trim($direccion);
        if ($direccion === '') {
            return [];
        }

        $q = implode(', ', array_filter(
            [$direccion, $distrito, $city, $region, $country],
            fn ($v): bool => filled($v),
        ));

        try {
            $params = [
                'q' => $q,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'accept-language' => 'es',
                'limit' => 10,
            ];
            if (filled($viewbox)) {
                $params['viewbox'] = $viewbox;
                $params['bounded'] = 1;
            }

            $response = $this->client()->get("{$this->baseUrl}/search", $params);
            if ($response->failed()) {
                return [];
            }

            $distritoLower = filled($distrito) ? mb_strtolower(trim($distrito)) : null;
            $out = [];

            foreach ($response->json() ?? [] as $x) {
                $a = $x['address'] ?? [];

                // Si nos dieron distrito, exigimos que aparezca en el resultado.
                if ($distritoLower !== null) {
                    $campos = array_filter(array_map('mb_strtolower', [
                        (string) ($a['city'] ?? ''),
                        (string) ($a['town'] ?? ''),
                        (string) ($a['village'] ?? ''),
                        (string) ($a['suburb'] ?? ''),
                        (string) ($a['municipality'] ?? ''),
                        (string) ($a['city_district'] ?? ''),
                    ]));

                    $match = false;
                    foreach ($campos as $c) {
                        if ($c !== '' && str_contains($c, $distritoLower)) {
                            $match = true;
                            break;
                        }
                    }
                    if (! $match) {
                        continue;
                    }
                }

                $out[] = [
                    'direccion' => (string) ($x['display_name'] ?? $direccion),
                    'lat' => isset($x['lat']) ? (float) $x['lat'] : null,
                    'lng' => isset($x['lon']) ? (float) $x['lon'] : null,
                    'distrito' => $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['suburb'] ?? $a['municipality'] ?? null,
                ];
            }

            return array_slice($out, 0, 5);
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }
}
