<?php

namespace App\Filament\Pages\Tenancy;

use App\Services\Tenancy\TenantProvisioner;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant as BaseRegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Página "Crear negocio": alta de un tenant nuevo. Quien lo crea queda como
 * dueño y el negocio nace con sucursal y métodos de pago listos para operar.
 */
class RegisterTenant extends BaseRegisterTenant
{
    public static function getLabel(): string
    {
        return 'Crear negocio';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre del negocio')
                ->placeholder('Ej: H2O Wanka')
                ->required()
                ->maxLength(255),

            Select::make('rubro')
                ->label('Rubro')
                ->options(['agua' => 'Agua', 'gas' => 'Gas / GLP'])
                ->default('agua')
                ->required(),
        ]);
    }

    protected function handleRegistration(array $data): Model
    {
        return app(TenantProvisioner::class)->create($data, auth()->user());
    }
}
