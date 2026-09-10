<?php

namespace App\Filament\Widgets;

use App\Models\SaleItem;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Productos más vendidos del mes (por cantidad), a partir de ventas cobradas.
 */
class TopProductos extends Widget
{
    protected string $view = 'filament.widgets.top-productos';

    protected static ?int $sort = -6;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    /** @return Collection<int, object{name:string, qty:int, total:float}> */
    public function topProductos(): Collection
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'cobrada')
            ->whereBetween('sales.paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('sale_items.product_name as name')
            ->selectRaw('SUM(sale_items.quantity) as qty')
            ->selectRaw('SUM(sale_items.unit_price_charged * sale_items.quantity) as total')
            ->groupBy('sale_items.product_name')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();
    }
}
