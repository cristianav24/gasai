<?php

namespace App\Filament\Resources\ContainerTypes;

use App\Filament\Resources\ContainerTypes\Pages\CreateContainerType;
use App\Filament\Resources\ContainerTypes\Pages\EditContainerType;
use App\Filament\Resources\ContainerTypes\Pages\ListContainerTypes;
use App\Filament\Resources\ContainerTypes\Schemas\ContainerTypeForm;
use App\Filament\Resources\ContainerTypes\Tables\ContainerTypesTable;
use App\Models\ContainerType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContainerTypeResource extends Resource
{
    protected static ?string $model = ContainerType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;

    protected static ?string $modelLabel = 'tipo de envase';

    protected static ?string $pluralModelLabel = 'tipos de envase';

    protected static ?string $navigationLabel = 'Tipos de envase';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración del negocio';

    protected static ?int $navigationSort = 9;

    public static function getRecordTitleAttribute(): ?string
    {
        return 'name';
    }

    public static function form(Schema $schema): Schema
    {
        return ContainerTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContainerTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContainerTypes::route('/'),
            'create' => CreateContainerType::route('/create'),
            'edit' => EditContainerType::route('/{record}/edit'),
        ];
    }
}
