<?php

namespace App\Services\Agent;

use App\Models\DeliveryZone;
use App\Models\Product;

/**
 * Cálculo de precios y totales. Regla dura del proyecto: los precios y totales
 * los calcula el código a partir de la base de datos, NUNCA el LLM. El modelo
 * solo comunica lo que este servicio devuelve.
 */
class OrderPricing
{
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
    public function calcular(array $items, ?int $zonaId = null): array
    {
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

        $costoEnvio = 0.0;
        if ($zonaId !== null) {
            $zona = DeliveryZone::where('active', true)->find($zonaId);
            if ($zona) {
                $costoEnvio = (float) $zona->delivery_fee;
            } else {
                $errores[] = "Zona de entrega {$zonaId} no existe o no está activa.";
            }
        }

        $subtotal = round($subtotal, 2);
        $total = round($subtotal + $costoEnvio, 2);

        return [
            'lineas' => $lineas,
            'subtotal' => $subtotal,
            'costo_envio' => $costoEnvio,
            'total' => $total,
            'errores' => $errores,
        ];
    }
}
