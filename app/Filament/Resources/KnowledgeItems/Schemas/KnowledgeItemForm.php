<?php

namespace App\Filament\Resources\KnowledgeItems\Schemas;

use App\Models\KnowledgeItem;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class KnowledgeItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->maxLength(255),

                Textarea::make('content')
                    ->label('Contenido')
                    ->helperText('Este texto se le da al agente como conocimiento de la empresa. Escribe claro y directo.')
                    ->required()
                    ->rows(6)
                    ->maxLength(KnowledgeItem::MAX_ITEM_CHARS)
                    ->columnSpanFull(),

                Toggle::make('active')
                    ->label('Activo')
                    ->helperText('Solo los items activos se le entregan al agente.')
                    ->default(true),
            ]);
    }
}
