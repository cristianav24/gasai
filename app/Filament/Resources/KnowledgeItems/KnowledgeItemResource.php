<?php

namespace App\Filament\Resources\KnowledgeItems;

use App\Filament\Resources\KnowledgeItems\Pages\CreateKnowledgeItem;
use App\Filament\Resources\KnowledgeItems\Pages\EditKnowledgeItem;
use App\Filament\Resources\KnowledgeItems\Pages\ListKnowledgeItems;
use App\Filament\Resources\KnowledgeItems\Schemas\KnowledgeItemForm;
use App\Filament\Resources\KnowledgeItems\Tables\KnowledgeItemsTable;
use App\Models\KnowledgeItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class KnowledgeItemResource extends Resource
{
    protected static ?string $model = KnowledgeItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'item de conocimiento';

    protected static ?string $pluralModelLabel = 'base de conocimiento';

    protected static ?string $navigationLabel = 'Base de conocimiento';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración del negocio';

    protected static ?int $navigationSort = 3;

    public static function getRecordTitleAttribute(): ?string
    {
        return 'title';
    }

    /** Badge con el uso actual; avisa en rojo si se pasa del límite. */
    public static function getNavigationBadge(): ?string
    {
        $total = KnowledgeItem::activeCharsTotal();

        return $total . '/' . KnowledgeItem::MAX_TOTAL_CHARS;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return KnowledgeItem::activeCharsTotal() > KnowledgeItem::MAX_TOTAL_CHARS
            ? 'danger'
            : 'gray';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Caracteres de conocimiento activos que se le entregan al agente.';
    }

    public static function form(Schema $schema): Schema
    {
        return KnowledgeItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KnowledgeItemsTable::configure($table);
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
            'index' => ListKnowledgeItems::route('/'),
            'create' => CreateKnowledgeItem::route('/create'),
            'edit' => EditKnowledgeItem::route('/{record}/edit'),
        ];
    }
}
