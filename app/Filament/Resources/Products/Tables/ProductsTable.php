<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Precio')
                    ->money('PEN')
                    ->sortable(),

                TextColumn::make('unit')
                    ->label('Unidad'),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'venta' => 'Venta',
                        'recarga' => 'Recarga',
                        default => $state,
                    })
                    ->color(fn (string $state): string => $state === 'recarga' ? 'info' : 'success'),

                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'venta' => 'Venta',
                        'recarga' => 'Recarga',
                    ]),

                TernaryFilter::make('active')
                    ->label('Activo'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
