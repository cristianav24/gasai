<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Las últimas ventas del negocio, con acceso rápido al ticket.
 */
class UltimasVentas extends TableWidget
{
    protected static ?int $sort = -7;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Últimas ventas')
            ->query(Sale::query()->latest('id')->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m H:i'),
                TextColumn::make('customer.name')->label('Cliente')->placeholder('Mostrador'),
                TextColumn::make('total')->label('Total')->money('PEN')->weight('bold'),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state): string => Sale::LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'cobrada' => 'success',
                        'en_espera' => 'warning',
                        'cancelada' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('ticket')
                    ->label('Ticket')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (Sale $record): string => route('ticket.sale', $record), shouldOpenInNewTab: true),
            ])
            ->emptyStateHeading('Aún no hay ventas')
            ->emptyStateDescription('Las ventas del POS aparecerán aquí.');
    }
}
