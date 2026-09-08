<?php

namespace App\Services\Agent\Tools;

use App\Models\Product;
use App\Services\Agent\AgentContext;

/**
 * Lista el catálogo activo con precios reales de la base de datos.
 */
class ListarProductos implements Tool
{
    public function name(): string
    {
        return 'listar_productos';
    }

    public function description(): string
    {
        return 'Devuelve el catálogo de productos activos con sus precios reales. '
            . 'Usa siempre estos precios; nunca los inventes.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),  // sin parámetros
        ];
    }

    public function handle(array $arguments, AgentContext $context): array
    {
        $productos = Product::where('active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p): array => [
                'producto_id' => $p->id,
                'nombre' => $p->name,
                'precio' => (float) $p->price,
                'unidad' => $p->unit,
                'tipo' => $p->type,
                'exige_envase_vacio' => $p->requires_empty,
            ])
            ->all();

        return ['productos' => $productos];
    }
}
