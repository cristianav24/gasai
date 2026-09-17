<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Services\Agent\OrderPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPricingDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::create([
            'name' => 'H2O', 'slug' => 'h2o', 'rubro' => 'agua',
            'delivery_center_lat' => 0, 'delivery_center_lng' => 0,
            'delivery_bands' => [['to_km' => 2, 'fee' => 0], ['to_km' => 4, 'fee' => 3]],
        ]);
    }

    public function test_el_precio_por_linea_incluye_el_envio_y_cuadra_con_el_total(): void
    {
        $t = $this->tenant();
        $recarga = Product::create(['tenant_id' => $t->id, 'name' => 'Recarga', 'price' => 10, 'unit' => 'unidad', 'type' => 'recarga', 'active' => true]);

        // ~2.9 km → banda "hasta 4 km" = S/ 3.
        $calc = app(OrderPricing::class)->calcular(
            [['producto_id' => $recarga->id, 'cantidad' => 1]], null, $t, 0.0, 0.02,
        );

        $this->assertSame(3.0, $calc['costo_envio']);
        $this->assertSame(13.0, $calc['total']);
        // El cliente ve "S/ 13" por la recarga (envío incluido), no "S/ 10 + envío".
        $this->assertSame(13.0, $calc['lineas'][0]['precio_con_envio']);
        $this->assertSame(13.0, $calc['lineas'][0]['importe_con_envio']);
    }

    public function test_dos_unidades_reparten_el_envio_y_siguen_cuadrando(): void
    {
        $t = $this->tenant();
        $recarga = Product::create(['tenant_id' => $t->id, 'name' => 'Recarga', 'price' => 10, 'unit' => 'unidad', 'type' => 'recarga', 'active' => true]);

        $calc = app(OrderPricing::class)->calcular(
            [['producto_id' => $recarga->id, 'cantidad' => 2]], null, $t, 0.0, 0.02,
        );

        $this->assertSame(3.0, $calc['costo_envio']);
        $this->assertSame(23.0, $calc['total']);
        // 2 unidades: importe con envío = 20 + 3 = 23, cuadra con el total.
        $this->assertSame(23.0, $calc['lineas'][0]['importe_con_envio']);
    }

    public function test_sin_envio_el_precio_con_envio_es_el_base(): void
    {
        $t = Tenant::create(['name' => 'X', 'slug' => 'x', 'rubro' => 'agua']); // sin config de distancia
        $recarga = Product::create(['tenant_id' => $t->id, 'name' => 'Recarga', 'price' => 10, 'unit' => 'unidad', 'type' => 'recarga', 'active' => true]);

        $calc = app(OrderPricing::class)->calcular([['producto_id' => $recarga->id, 'cantidad' => 1]], null, $t, 0.0, 0.0);

        $this->assertSame(0.0, $calc['costo_envio']);
        $this->assertSame(10.0, $calc['lineas'][0]['precio_con_envio']);
    }
}
