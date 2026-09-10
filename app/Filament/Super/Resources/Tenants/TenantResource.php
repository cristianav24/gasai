<?php

namespace App\Filament\Super\Resources\Tenants;

use App\Filament\Super\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Super\Resources\Tenants\Pages\EditTenant;
use App\Filament\Super\Resources\Tenants\Pages\ListTenants;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $modelLabel = 'negocio';

    protected static ?string $pluralModelLabel = 'negocios';

    protected static ?string $navigationLabel = 'Negocios';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Negocio')->schema([
                TextInput::make('name')->label('Nombre del negocio')->required()->maxLength(255),
                Select::make('rubro')->label('Rubro')
                    ->options(['agua' => 'Agua', 'gas' => 'Gas / GLP'])->default('agua')->required(),
                Toggle::make('tracks_containers')->label('Maneja envases retornables')
                    ->helperText('Actívalo si el negocio presta/da en garantía sus envases (típico en gas).'),
            ]),

            Section::make('Dueño')
                ->description('Se crea la cuenta del dueño del negocio. Si el correo ya existe, se le da acceso sin cambiar su contraseña.')
                ->schema([
                    TextInput::make('owner_name')->label('Nombre del dueño')->required()->dehydrated(false),
                    TextInput::make('owner_email')->label('Correo del dueño')->email()->required()->dehydrated(false),
                    TextInput::make('owner_password')->label('Contraseña inicial')->password()->revealable()
                        ->minLength(8)->required()->dehydrated(false)
                        ->helperText('Se usa solo si el correo es nuevo. Compártela con el dueño.'),
                ])
                ->visibleOn('create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->label('Negocio')->searchable()->sortable()->weight('bold'),
                TextColumn::make('slug')->label('Slug')->color('gray')->copyable(),
                TextColumn::make('rubro')->label('Rubro')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'gas' ? 'Gas' : 'Agua')
                    ->color(fn (string $state): string => $state === 'gas' ? 'warning' : 'info'),
                IconColumn::make('tracks_containers')->label('Envases')->boolean(),
                TextColumn::make('users_count')->label('Usuarios')->counts('users')->alignCenter(),
                TextColumn::make('created_at')->label('Alta')->date('d/m/Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('rubro')->label('Rubro')->options(['agua' => 'Agua', 'gas' => 'Gas / GLP']),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'create' => CreateTenant::route('/create'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }
}
