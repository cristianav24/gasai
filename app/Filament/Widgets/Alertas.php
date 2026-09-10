<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Inventario;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use Filament\Widgets\Widget;

/**
 * Alertas operativas del negocio: stock agotado/bajo y pedidos activos sin
 * repartidor asignado.
 */
class Alertas extends Widget
{
    protected string $view = 'filament.widgets.alertas';

    protected static ?int $sort = -6;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    /** @return array{agotados: array<int,string>, bajos: array<int,array{name:string,qty:int}>, sinRepartidor: int} */
    public function alertas(): array
    {
        $low = Inventario::LOW_STOCK;

        $stock = StockLevel::query()
            ->selectRaw('product_id, SUM(quantity) as qty')
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        $agotados = [];
        $bajos = [];
        foreach (Product::where('active', true)->orderBy('name')->get(['id', 'name']) as $p) {
            $qty = (int) ($stock[$p->id] ?? 0);
            if ($qty <= 0) {
                $agotados[] = $p->name;
            } elseif ($qty <= $low) {
                $bajos[] = ['name' => $p->name, 'qty' => $qty];
            }
        }

        $sinRepartidor = Order::whereIn('status', ['confirmado', 'en_ruta'])
            ->whereNull('courier_id')
            ->count();

        return [
            'agotados' => $agotados,
            'bajos' => $bajos,
            'sinRepartidor' => $sinRepartidor,
        ];
    }
}
