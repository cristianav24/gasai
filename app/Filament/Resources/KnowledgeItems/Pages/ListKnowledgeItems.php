<?php

namespace App\Filament\Resources\KnowledgeItems\Pages;

use App\Filament\Resources\KnowledgeItems\KnowledgeItemResource;
use App\Models\KnowledgeItem;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListKnowledgeItems extends ListRecords
{
    protected static string $resource = KnowledgeItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getSubheading(): string|HtmlString|null
    {
        $total = KnowledgeItem::activeCharsTotal();
        $max = KnowledgeItem::MAX_TOTAL_CHARS;

        if ($total > $max) {
            return new HtmlString(
                '<span style="color:#dc2626;font-weight:600;">⚠️ Te pasaste del límite: '
                . number_format($total) . ' de ' . number_format($max)
                . ' caracteres. Desactiva o acorta algunos items; el agente podría no recibir todo.</span>'
            );
        }

        return 'Conocimiento activo: ' . number_format($total) . ' de ' . number_format($max) . ' caracteres.';
    }
}
