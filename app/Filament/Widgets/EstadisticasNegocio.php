<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Resumen del negocio en el Escritorio: ventas de hoy, del mes, pedidos activos
 * y clientes. Todo acotado al tenant por el global scope de los modelos.
 */
class EstadisticasNegocio extends StatsOverviewWidget
{
    protected static ?int $sort = -9; // Debajo del checklist, arriba de todo lo demás.

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $ventasHoy = Sale::where('status', 'cobrada')->whereDate('paid_at', today());
        $totalHoy = (float) (clone $ventasHoy)->sum('total');
        $countHoy = (clone $ventasHoy)->count();

        $totalMes = (float) Sale::where('status', 'cobrada')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total');

        $pendientes = Order::where('status', 'pendiente')->count();
        $enRuta = Order::where('status', 'en_ruta')->count();
        $activos = Order::whereIn('status', ['pendiente', 'confirmado', 'en_ruta'])->count();

        $clientes = Customer::count();

        // Tendencia de los últimos 7 días para el mini-gráfico de la primera tarjeta.
        $tendencia = collect(range(6, 0))
            ->map(fn (int $d): float => (float) Sale::where('status', 'cobrada')
                ->whereDate('paid_at', today()->subDays($d))
                ->sum('total'))
            ->all();

        return [
            Stat::make('Ventas de hoy', 'S/ ' . number_format($totalHoy, 2))
                ->description($countHoy . ' ' . ($countHoy === 1 ? 'venta' : 'ventas'))
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->chart($tendencia)
                ->color('success'),

            Stat::make('Cobrado este mes', 'S/ ' . number_format($totalMes, 2))
                ->description(Carbon::now()->locale('es')->isoFormat('MMMM YYYY'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Pedidos activos', (string) $activos)
                ->description("{$pendientes} pendientes · {$enRuta} en ruta")
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),

            Stat::make('Clientes', (string) $clientes)
                ->description('registrados')
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),
        ];
    }
}
