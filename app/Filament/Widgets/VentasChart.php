<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Widgets\ChartWidget;

/**
 * Ventas cobradas por día en las últimas dos semanas.
 */
class VentasChart extends ChartWidget
{
    protected static ?int $sort = -8;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public function getHeading(): ?string
    {
        return 'Ventas de los últimos 14 días';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $dias = collect(range(13, 0))->map(fn (int $d) => today()->subDays($d));

        $montos = $dias->map(fn ($dia): float => (float) Sale::where('status', 'cobrada')
            ->whereDate('paid_at', $dia)
            ->sum('total'));

        return [
            'datasets' => [
                [
                    'label' => 'S/ cobrado',
                    'data' => $montos->all(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $dias->map(fn ($d): string => $d->format('d/m'))->all(),
        ];
    }
}
