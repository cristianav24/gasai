<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\Delivery\DeliveryPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryPricingTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(array $extra = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua',
            'delivery_center_lat' => 0, 'delivery_center_lng' => 0,
            'delivery_bands' => [
                ['to_km' => 2, 'fee' => 0],
                ['to_km' => 4, 'fee' => 2],
                ['to_km' => 6, 'fee' => 3],
            ],
        ], $extra));
    }

    public function test_radio_gratis_no_cobra(): void
    {
        $q = app(DeliveryPricing::class)->quote($this->tenant(), 0.0, 0.005, 10); // ~0.7 km
        $this->assertSame('distancia', $q['metodo']);
        $this->assertTrue($q['cubierto']);
        $this->assertSame(0.0, $q['fee']);
    }

    public function test_cobra_segun_la_banda(): void
    {
        // ~2.9 km (0.02° lng ≈ 2.23 km * 1.3) → banda "hasta 4 km" = S/ 2.
        $q = app(DeliveryPricing::class)->quote($this->tenant(), 0.0, 0.02, 10);
        $this->assertTrue($q['cubierto']);
        $this->assertSame(2.0, $q['fee']);
        $this->assertGreaterThan(2.0, $q['distancia_km']);
        $this->assertLessThan(4.0, $q['distancia_km']);
    }

    public function test_fuera_de_la_ultima_banda_es_fuera_de_cobertura(): void
    {
        $q = app(DeliveryPricing::class)->quote($this->tenant(), 0.0, 0.06, 10); // ~8.7 km
        $this->assertFalse($q['cubierto']);
        $this->assertNull($q['fee']);
    }

    public function test_envio_gratis_por_monto(): void
    {
        $t = $this->tenant(['delivery_free_over' => 50]);
        $q = app(DeliveryPricing::class)->quote($t, 0.0, 0.02, 60); // lejos, pero pedido grande
        $this->assertTrue($q['gratis_por_monto']);
        $this->assertSame(0.0, $q['fee']);
    }

    public function test_sin_coordenadas_cae_a_la_zona(): void
    {
        $q = app(DeliveryPricing::class)->quote($this->tenant(), null, null, 10);
        $this->assertSame('sin_ubicacion', $q['metodo']);
        $this->assertNull($q['fee']);
    }

    public function test_sin_config_de_distancia_cae_a_la_zona(): void
    {
        $t = Tenant::create(['name' => 'X', 'slug' => 'x', 'rubro' => 'agua']); // sin centro ni bandas
        $q = app(DeliveryPricing::class)->quote($t, 0.0, 0.02, 10);
        $this->assertSame('sin_ubicacion', $q['metodo']);
    }
}
