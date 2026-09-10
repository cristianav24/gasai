<?php

namespace App\Services\Geo;

use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

/**
 * Reverse geocoding con Nominatim (OpenStreetMap): convierte coordenadas en una
 * dirección legible. Gratis y sin API key; su política pide un User-Agent
 * identificable y un uso moderado (ok para las ubicaciones ocasionales del bot).
 */
class Geocoder
{
    public function __construct(
        private HttpFactory $http,
        private string $endpoint = 'https://nominatim.openstreetmap.org/reverse',
    ) {}

    /**
     * Devuelve la dirección aproximada de unas coordenadas, o null si falla.
     */
    public function reverse(float $lat, float $lng): ?string
    {
        try {
            $response = $this->http
                ->withHeaders(['User-Agent' => 'GasAI/1.0 (soporte@tandix.app)'])
                ->timeout(6)
                ->get($this->endpoint, [
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
}
