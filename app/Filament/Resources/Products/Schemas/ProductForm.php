<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('Descripción')
                    ->rows(3)
                    ->columnSpanFull(),

                TextInput::make('price')
                    ->label('Precio de lista')
                    ->helperText('Referencial. En el POS se podrá ajustar por venta.')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('S/'),

                TextInput::make('unit')
                    ->label('Unidad')
                    ->placeholder('bidón, balón, unidad…')
                    ->required()
                    ->default('unidad')
                    ->maxLength(50),

                \Filament\Forms\Components\Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'venta' => 'Venta (envase nuevo)',
                        'recarga' => 'Recarga',
                    ])
                    ->default('venta')
                    ->required()
                    ->live(),

                Toggle::make('requires_empty')
                    ->label('Exige envase vacío')
                    ->helperText('El cliente debe entregar el envase vacío (típico en recargas).')
                    ->default(false),

                \Filament\Forms\Components\Select::make('container_type_id')
                    ->label('Tipo de envase retornable')
                    ->helperText('Si este producto usa un envase retornable (bidón/balón), elígelo aquí.')
                    ->options(fn (): array => \App\Models\ContainerType::where('active', true)->pluck('name', 'id')->all())
                    ->searchable(),

                Toggle::make('active')
                    ->label('Activo')
                    ->default(true),
            ]);
    }
}
