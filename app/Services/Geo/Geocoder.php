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

        // Nominatim tiene datos pobres de numeración y nombres oficiales en Perú:
        // el número de casa, la región con tilde o el prefijo (jr/av) suelen tumbar
        // la búsqueda a cero. Por eso probamos de lo más específico a lo más simple
        // y nos quedamos con el primer intento que devuelva algo.
        $calle = trim($direccion);
        $sinNumero = $this->stripHouseNumber($calle);
        $core = $this->stripStreetPrefix($sinNumero);

        $intentos = [];
        foreach ([
            [$calle, $distrito, $city, $region, $country],
            [$sinNumero, $distrito, $city, $region, $country],
            [$sinNumero, $distrito, $city, $country],
            [$core, $distrito, $city, $country],
            [$core, $distrito, $city],
            [$core, $city, $country],
        ] as $partes) {
            $q = implode(', ', array_filter($partes, fn ($v): bool => filled($v)));
            if ($q !== '' && ! in_array($q, $intentos, true)) {
                $intentos[] = $q;
            }
        }

        foreach ($intentos as $q) {
            $out = $this->run($q, $distrito, $direccion, $viewbox);
            if (! empty($out)) {
                return $out;
            }
        }

        return [];
    }

    /**
     * Una consulta a Nominatim con filtro por distrito. El viewbox se usa solo
     * como sesgo (sin bounded), para no excluir resultados válidos cercanos.
     *
     * @return array<int, array{direccion: string, lat: ?float, lng: ?float, distrito: ?string}>
     */
    private function run(string $q, ?string $distrito, string $original, ?string $viewbox): array
    {
        try {
            $params = [
                'q' => $q,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'accept-language' => 'es',
                'limit' => 10,
            ];
            if (filled($viewbox)) {
                $params['viewbox'] = $viewbox; // sesgo, no límite duro
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
                        // Bidireccional: "chilca" coincide con "chilca, huancayo" y viceversa.
                        if ($c !== '' && (str_contains($c, $distritoLower) || str_contains($distritoLower, $c))) {
                            $match = true;
                            break;
                        }
                    }
                    if (! $match) {
                        continue;
                    }
                }

                $out[] = [
                    'direccion' => (string) ($x['display_name'] ?? $original),
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

    /** Quita un número de casa al final (ej. "Gonzales Prada 753" -> "Gonzales Prada"). */
    private function stripHouseNumber(string $s): string
    {
        return trim((string) preg_replace('/[\s,]+(n[°ºo]\.?\s*|nro\.?\s*|#\s*)?\d{1,5}[a-z]?\s*$/iu', '', $s));
    }

    /** Quita el prefijo de tipo de vía (jr, av, calle, pasaje…) que suele confundir a OSM. */
    private function stripStreetPrefix(string $s): string
    {
        $out = (string) preg_replace(
            '/^\s*(jr|jiron|jirón|av|avda|avenida|calle|ca|pasaje|psje|psj|pje|prol|prolongacion|prolongación|urb|urbanizacion|urbanización|mz|manzana)\.?\s+/iu',
            '',
            $s,
        );

        return trim($out) !== '' ? trim($out) : $s;
    }
}
