<?php

namespace App\Filament\Resources\Sales;

use App\Filament\Resources\Sales\Pages\ListSales;
use App\Filament\Resources\Sales\Tables\SalesTable;
use App\Models\Sale;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Ventas: listado de solo lectura de todo lo cobrado (o en espera) en el POS.
 * Sin crear/editar aquí: las ventas nacen en el Punto de Venta.
 */
class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $modelLabel = 'venta';

    protected static ?string $pluralModelLabel = 'ventas';

    protected static ?string $navigationLabel = 'Ventas';

    protected static ?int $navigationSort = 7;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return SalesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSales::route('/'),
        ];
    }
}
