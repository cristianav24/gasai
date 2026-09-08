<?php

namespace App\Filament\Resources\DeliveryZones\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DeliveryZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre de la zona')
                    ->required()
                    ->maxLength(255),

                TextInput::make('delivery_fee')
                    ->label('Costo de envío')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('S/'),

                Textarea::make('coverage')
                    ->label('Cobertura')
                    ->helperText('Describe qué calles, urbanizaciones o referencias cubre esta zona.')
                    ->rows(3)
                    ->columnSpanFull(),

                Toggle::make('active')
                    ->label('Activa')
                    ->default(true),
            ]);
    }
}
