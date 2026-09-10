<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Models\Sale;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->placeholder('Mostrador'),

                TextColumn::make('order_id')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Pedido' : 'Mostrador')
                    ->color(fn ($state): string => $state ? 'info' : 'gray'),

                TextColumn::make('paymentMethod.name')
                    ->label('Pago')
                    ->placeholder('—'),

                TextColumn::make('items_count')
                    ->label('Ítems')
                    ->counts('items')
                    ->alignCenter(),

                TextColumn::make('discount_total')
                    ->label('Descuento')
                    ->money('PEN')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('PEN')
                    ->sortable()
                    ->weight('bold')
                    ->summarize(Sum::make()->label('Total')->money('PEN')),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Sale::LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'cobrada' => 'success',
                        'en_espera' => 'warning',
                        'cancelada' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('cashier.name')
                    ->label('Cajero')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Sale::LABELS),

                SelectFilter::make('payment_method_id')
                    ->label('Método de pago')
                    ->relationship('paymentMethod', 'name'),

                Filter::make('fecha')
                    ->schema([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['hasta'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->modalHeading(fn (Sale $record): string => "Venta #{$record->id}")
                    ->modalSubmitAction(false)
                    ->modalContent(fn (Sale $record) => view('filament.sales.detail', [
                        'sale' => $record->load(['items', 'customer', 'paymentMethod', 'cashier', 'order', 'branch']),
                    ])),

                Action::make('ticket')
                    ->label('Ticket')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (Sale $record): string => route('ticket.sale', $record), shouldOpenInNewTab: true),
            ]);
    }
}
