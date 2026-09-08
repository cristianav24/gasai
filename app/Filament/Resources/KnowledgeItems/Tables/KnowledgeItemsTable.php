<?php

namespace App\Filament\Resources\KnowledgeItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class KnowledgeItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('content')
                    ->label('Contenido')
                    ->limit(60)
                    ->wrap(),

                TextColumn::make('content')
                    ->label('Caracteres')
                    ->state(fn ($record): int => mb_strlen($record->content))
                    ->badge()
                    ->color('gray'),

                IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
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
