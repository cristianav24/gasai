<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->maxLength(255)
                    ->helperText('Si viene de WhatsApp, aquí se guarda su nombre de perfil.'),

                TextInput::make('phone')
                    ->label('Teléfono')
                    ->tel()
                    ->maxLength(30)
                    ->helperText('Con código de país. Ej: +51987654321.'),

                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(3)
                    ->columnSpanFull()
                    ->placeholder('Ej. cliente frecuente, referencia del domicilio, preferencias…'),
            ]);
    }
}
