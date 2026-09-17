<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
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

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->placeholder('—'),

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
