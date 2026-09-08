<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->placeholder('Efectivo, Yape, Plin, Transferencia, Crédito…')
                    ->required()
                    ->maxLength(255),

                Toggle::make('is_cash')
                    ->label('Es efectivo')
                    ->helperText('Marca los métodos que cuentan para el arqueo de caja (ej. Efectivo).')
                    ->default(false),

                Toggle::make('active')
                    ->label('Activo')
                    ->default(true),
            ]);
    }
}
