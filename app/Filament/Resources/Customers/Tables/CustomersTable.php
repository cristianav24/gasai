<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Cliente')
                    ->state(fn (Customer $record): string => $record->displayName())
                    ->searchable(['name', 'phone'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'cliente' => 'Cliente', 'lead' => 'Lead', default => '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'cliente' => 'success', 'lead' => 'warning', default => 'gray',
                    }),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('direccion')
                    ->label('Dirección')
                    ->state(fn (Customer $record): ?string => $record->primaryAddress()?->address)
                    ->limit(40)
                    ->tooltip(fn (Customer $record): ?string => $record->primaryAddress()?->address)
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('orders_count')
                    ->label('Pedidos')
                    ->counts('orders')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('sales_sum_total')
                    ->label('Gastado')
                    ->sum('sales', 'total')
                    ->money('PEN')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('container_balances_sum_balance')
                    ->label('Envases')
                    ->sum('containerBalances', 'balance')
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'warning' : 'gray'),

                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options(['cliente' => 'Cliente', 'lead' => 'Lead']),

                TernaryFilter::make('con_direccion')
                    ->label('Dirección')
                    ->placeholder('Todos')
                    ->trueLabel('Con dirección')
                    ->falseLabel('Sin dirección')
                    ->queries(
                        true: fn ($query) => $query->whereHas('addresses'),
                        false: fn ($query) => $query->whereDoesntHave('addresses'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
