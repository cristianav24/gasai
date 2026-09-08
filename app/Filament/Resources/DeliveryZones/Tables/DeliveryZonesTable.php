<?php

namespace App\Filament\Resources\DeliveryZones\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DeliveryZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Zona')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('delivery_fee')
                    ->label('Costo de envío')
                    ->money('PEN')
                    ->sortable(),

                TextColumn::make('coverage')
                    ->label('Cobertura')
                    ->limit(50)
                    ->wrap(),

                IconColumn::make('active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Activa'),
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
