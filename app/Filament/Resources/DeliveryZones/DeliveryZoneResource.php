<?php

namespace App\Filament\Resources\DeliveryZones;

use App\Filament\Resources\DeliveryZones\Pages\CreateDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\EditDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\ListDeliveryZones;
use App\Filament\Resources\DeliveryZones\Schemas\DeliveryZoneForm;
use App\Filament\Resources\DeliveryZones\Tables\DeliveryZonesTable;
use App\Models\DeliveryZone;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DeliveryZoneResource extends Resource
{
    protected static ?string $model = DeliveryZone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $modelLabel = 'zona de entrega';

    protected static ?string $pluralModelLabel = 'zonas de entrega';

    protected static ?string $navigationLabel = 'Zonas de entrega';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración del negocio';

    protected static ?int $navigationSort = 2;

    public static function getRecordTitleAttribute(): ?string
    {
        return 'name';
    }

    public static function form(Schema $schema): Schema
    {
        return DeliveryZoneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeliveryZonesTable::configure($table);
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
            'index' => ListDeliveryZones::route('/'),
            'create' => CreateDeliveryZone::route('/create'),
            'edit' => EditDeliveryZone::route('/{record}/edit'),
        ];
    }
}
