<?php

namespace App\Filament\Resources\ContainerTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ContainerTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->placeholder('Bidón 20L, Balón 10kg…')
                    ->required()
                    ->maxLength(255),

                Toggle::make('active')->label('Activo')->default(true),
            ]);
    }
}
