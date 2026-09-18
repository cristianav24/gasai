<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Tenant;
use App\Services\Agent\AgentContext;
use App\Models\Address;
use App\Services\Agent\Tools\GuardarDireccion;
use App\Services\Agent\Tools\ValidarDireccion;
use App\Services\Geo\Geocoder;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocoderTest extends TestCase
{
    use RefreshDatabase;

    private function fakeNominatim(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'display_name' => 'Jirón Gonzáles Prada 753, El Tambo, Huancayo, Junín, Perú',
                    'lat' => '-12.0581', 'lon' => '-75.2103',
                    'address' => ['road' => 'Jirón Gonzáles Prada', 'city' => 'El Tambo'],
                ],
                [
                    'display_name' => 'Jirón Gonzáles Prada, Chilca, Huancayo',
                    'lat' => '-12.0900', 'lon' => '-75.2000',
                    'address' => ['road' => 'Jirón Gonzáles Prada', 'city' => 'Chilca'],
                ],
            ], 200),
        ]);
    }

    private function geocoder(): Geocoder
    {
        return new Geocoder(app(HttpFactory::class));
    }

    public function test_search_filtra_por_distrito_y_devuelve_coordenadas(): void
    {
        $this->fakeNominatim();

        $res = $this->geocoder()->search(
            'Jirón Gonzáles Prada', 'El Tambo', 'Huancayo', 'Junín', 'Perú', '-75.30,-11.95,-75.14,-12.13',
        );

        $this->assertCount(1, $res); // solo el de El Tambo
        $this->assertSame('El Tambo', $res[0]['distrito']);
        $this->assertEqualsWithDelta(-12.0581, $res[0]['lat'], 0.001);
        $this->assertEqualsWithDelta(-75.2103, $res[0]['lng'], 0.001);
    }

    public function test_search_vacio_si_ningun_resultado_coincide_con_el_distrito(): void
    {
        $this->fakeNominatim();

        $res = $this->geocoder()->search('Jirón Gonzáles Prada', 'San Carlos', 'Huancayo', 'Junín', 'Perú');

        $this->assertSame([], $res);
    }

    public function test_search_reintenta_sin_numero_de_casa_cuando_el_primero_falla(): void
    {
        // Nominatim no tiene numeración en Huancayo: el 1er intento (con "753")
        // sale vacío y el 2do (sin número) encuentra la calle.
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::sequence()
                ->push([], 200)
                ->push([[
                    'display_name' => 'Jirón Manuel Gonzales Prada, El Tambo, Huancayo, Junín, Perú',
                    'lat' => '-12.0570', 'lon' => '-75.2322',
                    'address' => ['road' => 'Jirón Manuel Gonzales Prada', 'city' => 'El Tambo'],
                ]], 200),
        ]);

        $res = $this->geocoder()->search(
            'jr gonzales prada 753', 'El Tambo', 'Huancayo', 'Junín', 'Perú', '-75.30,-11.95,-75.14,-12.13',
        );

        $this->assertCount(1, $res);
        $this->assertSame('El Tambo', $res[0]['distrito']);
        $this->assertEqualsWithDelta(-12.0570, $res[0]['lat'], 0.001);
    }

    public function test_guardar_direccion_geocodifica_sola_cuando_no_le_pasan_coordenadas(): void
    {
        $this->fakeNominatim();

        $tenant = Tenant::create([
            'name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua',
            'geo_city' => 'Huancayo', 'geo_region' => 'Junín', 'geo_country' => 'Perú',
        ]);
        $conv = Conversation::create(['tenant_id' => $tenant->id, 'channel' => 'whatsapp', 'status' => 'bot']);
        $ctx = new AgentContext($tenant, $conv, null);

        // Sin lat/lng: la herramienta los resuelve sola.
        $res = app(GuardarDireccion::class)->handle(
            ['direccion' => 'Jirón Gonzáles Prada', 'distrito' => 'El Tambo'],
            $ctx,
        );

        $this->assertTrue($res['ok']);
        $this->assertTrue($res['con_ubicacion']);
        $addr = Address::withoutGlobalScopes()->find($res['direccion_id']);
        $this->assertNotNull($addr->lat);
        $this->assertEqualsWithDelta(-12.0581, (float) $addr->lat, 0.001);
    }

    public function test_tool_validar_direccion_usa_la_zona_del_negocio(): void
    {
        $this->fakeNominatim();

        $tenant = Tenant::create([
            'name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua',
            'geo_city' => 'Huancayo', 'geo_region' => 'Junín', 'geo_country' => 'Perú',
            'geo_viewbox' => '-75.30,-11.95,-75.14,-12.13',
        ]);
        $conv = Conversation::create(['tenant_id' => $tenant->id, 'channel' => 'whatsapp', 'status' => 'bot']);
        $ctx = new AgentContext($tenant, $conv, null);

        $res = app(ValidarDireccion::class)->handle(
            ['direccion' => 'Jirón Gonzáles Prada', 'distrito' => 'El Tambo'],
            $ctx,
        );

        $this->assertTrue($res['encontrado']);
        $this->assertSame('El Tambo', $res['opciones'][0]['distrito']);
    }
}
