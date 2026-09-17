<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Address;
use App\Models\ContainerBalance;
use App\Models\Customer;
use App\Models\Order;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cliente')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('Nombre')
                        ->state(fn (Customer $record): string => $record->displayName()),
                    TextEntry::make('phone')->label('Teléfono')->placeholder('—'),
                    TextEntry::make('wa_user_id')->label('ID de WhatsApp')->placeholder('—'),
                    TextEntry::make('pedidos')->label('Pedidos')
                        ->state(fn (Customer $record): int => $record->orders()->count()),
                    TextEntry::make('gastado')->label('Total gastado')
                        ->money('PEN')
                        ->state(fn (Customer $record): float => (float) $record->sales()->where('status', 'cobrada')->sum('total')),
                    TextEntry::make('notes')->label('Notas')->placeholder('—')->columnSpanFull(),
                ]),

            Section::make('Direcciones')
                ->schema([
                    RepeatableEntry::make('addresses')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('address')->label('Dirección'),
                            TextEntry::make('reference')->label('Referencia')->placeholder('—'),
                            TextEntry::make('mapa')->label('Mapa')
                                ->state(fn (Address $record): ?string => $record->mapUrl())
                                ->url(fn (Address $record): ?string => $record->mapUrl(), true)
                                ->placeholder('sin ubicación'),
                        ])
                        ->columns(3),
                ])
                ->visible(fn (Customer $record): bool => $record->addresses()->exists()),

            Section::make('Pedidos')
                ->schema([
                    RepeatableEntry::make('orders')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('numero')->label('N°')
                                ->state(fn (Order $record): string => '#' . $record->displayNumber()),
                            TextEntry::make('scheduled_date')->label('Fecha')->date('d/m/Y')->placeholder('—'),
                            TextEntry::make('status')->label('Estado')->badge()
                                ->formatStateUsing(fn (string $state): string => Order::LABELS[$state] ?? $state),
                            TextEntry::make('total')->label('Total')->money('PEN'),
                        ])
                        ->columns(4),
                ])
                ->visible(fn (Customer $record): bool => $record->orders()->exists()),

            Section::make('Saldo de envases')
                ->schema([
                    RepeatableEntry::make('containerBalances')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('containerType.name')->label('Tipo'),
                            TextEntry::make('balance')->label('Saldo (bidones que tiene el cliente)'),
                        ])
                        ->columns(2),
                ])
                ->visible(fn (Customer $record): bool => $record->containerBalances()->where('balance', '!=', 0)->exists()),
        ]);
    }
}
