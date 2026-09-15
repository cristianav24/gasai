<?php

namespace App\Services\Agent;

use App\Models\DeliveryZone;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Delivery\DeliveryPricing;

/**
 * Cálculo de precios y totales. Regla dura del proyecto: los precios y totales
 * los calcula el código a partir de la base de datos, NUNCA el LLM. El modelo
 * solo comunica lo que este servicio devuelve.
 */
class OrderPricing
{
    public function __construct(private DeliveryPricing $delivery) {}

    /**
     * @param  array<int, array{producto_id?: int, cantidad?: int}>  $items
     * @return array{
     *   lineas: array<int, array<string, mixed>>,
     *   subtotal: float,
     *   costo_envio: float,
     *   total: float,
     *   errores: array<int, string>
     * }
     */
    public function calcular(
        array $items,
        ?int $zonaId = null,
        ?Tenant $tenant = null,
        ?float $lat = null,
        ?float $lng = null,
    ): array {
        $lineas = [];
        $errores = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $productId = (int) ($item['producto_id'] ?? 0);
            $cantidad = (int) ($item['cantidad'] ?? 0);

            if ($cantidad < 1) {
                $errores[] = "Cantidad inválida para el producto {$productId}.";
                continue;
            }

            // Precio tomado de la BD (acotado al tenant por el global scope).
            $producto = Product::where('active', true)->find($productId);

            if (! $producto) {
                $errores[] = "Producto {$productId} no existe o no está activo.";
                continue;
            }

            $precio = (float) $producto->price;
            $importe = round($precio * $cantidad, 2);
            $subtotal += $importe;

            $lineas[] = [
                'producto_id' => $producto->id,
                'nombre' => $producto->name,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'importe' => $importe,
            ];
        }

        $subtotal = round($subtotal, 2);

        $costoEnvio = 0.0;
        $distanciaKm = null;
        $cubierto = true;
        $gratisPorMonto = false;
        $usoDistancia = false;

        // Cobro por distancia (si el negocio lo configuró y hay coordenadas).
        if ($tenant !== null) {
            $q = $this->delivery->quote($tenant, $lat, $lng, $subtotal);
            if ($q['metodo'] === 'distancia') {
                $usoDistancia = true;
                $distanciaKm = $q['distancia_km'];
                $cubierto = $q['cubierto'];
                $gratisPorMonto = $q['gratis_por_monto'];

                if (! $cubierto) {
                    $errores[] = 'La dirección está fuera del área de cobertura de reparto.';
                } else {
                    $costoEnvio = (float) $q['fee'];
                }
            }
        }

        // Respaldo: tarifa fija por zona (clientes sin ubicación o sin config de distancia).
        if (! $usoDistancia && $zonaId !== null) {
            $zona = DeliveryZone::where('active', true)->find($zonaId);
            if ($zona) {
                $costoEnvio = (float) $zona->delivery_fee;
            } else {
                $errores[] = "Zona de entrega {$zonaId} no existe o no está activa.";
            }
        }

        $total = round($subtotal + $costoEnvio, 2);

        return [
            'lineas' => $lineas,
            'subtotal' => $subtotal,
            'costo_envio' => $costoEnvio,
            'total' => $total,
            'distancia_km' => $distanciaKm,
            'cubierto' => $cubierto,
            'gratis_por_monto' => $gratisPorMonto,
            'errores' => $errores,
        ];
    }
}
