<?php

namespace App\Filament\Pages;

use App\Models\ContainerBalance;
use App\Models\ContainerMovement;
use App\Models\ContainerType;
use App\Models\Customer;
use App\Services\Containers\ContainerService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Envases retornables: saldo por cliente y registro de devoluciones.
 * Las entregas suman al saldo automáticamente al entregar el pedido.
 */
class Envases extends Page
{
    protected string $view = 'filament.pages.envases';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;

    protected static ?string $title = 'Envases';

    protected static ?string $navigationLabel = 'Envases';

    protected static ?int $navigationSort = 8;

    /** Clientes con saldo de envases distinto de cero. */
    public function balances(): Collection
    {
        return ContainerBalance::query()
            ->with(['customer', 'containerType'])
            ->where('balance', '!=', 0)
            ->get()
            ->groupBy('customer_id');
    }

    /**
     * Fecha del último movimiento de envases por cliente (para mostrar “desde
     * cuándo” el cliente tiene envases nuestros).
     *
     * @return array<int, string>
     */
    public function lastMovements(): array
    {
        return ContainerMovement::query()
            ->selectRaw('customer_id, MAX(created_at) as ultimo')
            ->groupBy('customer_id')
            ->pluck('ultimo', 'customer_id')
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('devolucion')
                ->label('Registrar devolución')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->form([
                    Select::make('customer_id')->label('Cliente')
                        ->options(fn (): array => Customer::query()->orderBy('name')->get()
                            ->mapWithKeys(fn (Customer $c): array => [$c->id => ($c->name ?? 'Sin nombre') . ' — ' . $c->phone])
                            ->all())
                        ->searchable()->required(),
                    Select::make('container_type_id')->label('Tipo de envase')
                        ->options(fn (): array => ContainerType::where('active', true)->pluck('name', 'id')->all())
                        ->required(),
                    TextInput::make('quantity')->label('Cantidad devuelta')
                        ->numeric()->minValue(1)->required(),
                ])
                ->action(function (array $data): void {
                    app(ContainerService::class)->registerReturn(
                        Filament::getTenant()->getKey(),
                        (int) $data['customer_id'],
                        (int) $data['container_type_id'],
                        (int) $data['quantity'],
                    );

                    Notification::make()->success()->title('Devolución registrada')->send();
                }),
        ];
    }
}
