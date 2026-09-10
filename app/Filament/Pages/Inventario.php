<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockLevel;
use App\Services\Stock\StockService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Inventario por sucursal: existencias, ajustes manuales y transferencias.
 * El descuento por entrega ocurre automáticamente al entregar el pedido.
 */
class Inventario extends Page
{
    protected string $view = 'filament.pages.inventario';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $title = 'Inventario';

    protected static ?string $navigationLabel = 'Inventario';

    protected static ?int $navigationSort = 7;

    /** Umbral de stock bajo (para el aviso ámbar). */
    public const LOW_STOCK = 5;

    /** Búsqueda por nombre de producto. */
    public string $search = '';

    /** @return Collection<int, Branch> */
    public function branches(): Collection
    {
        return Branch::orderBy('id')->get();
    }

    /** @return Collection<int, Product> */
    public function products(): Collection
    {
        return Product::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'ilike', '%' . $this->search . '%'))
            ->orderBy('name')
            ->get();
    }

    /** Todos los productos (sin filtro) para calcular el resumen. */
    public function allProducts(): Collection
    {
        return Product::orderBy('name')->get();
    }

    /** Stock actual como mapa "branchId-productId" => cantidad. */
    public function stockMap(): array
    {
        return StockLevel::query()
            ->get()
            ->mapWithKeys(fn (StockLevel $s): array => ["{$s->branch_id}-{$s->product_id}" => $s->quantity])
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajustar')
                ->label('Ajustar stock')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->form([
                    Select::make('branch_id')->label('Sucursal')
                        ->options(fn () => $this->branches()->pluck('name', 'id'))->required(),
                    Select::make('product_id')->label('Producto')
                        ->options(fn () => $this->products()->pluck('name', 'id'))->required(),
                    TextInput::make('delta')->label('Cantidad (+ ingresa, − retira)')
                        ->numeric()->required()
                        ->helperText('Ej. 10 para cargar, -3 para descontar.'),
                    TextInput::make('reason')->label('Motivo')->required()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    app(StockService::class)->adjust(
                        Filament::getTenant()->getKey(),
                        (int) $data['branch_id'],
                        (int) $data['product_id'],
                        (int) $data['delta'],
                        'ajuste',
                        $data['reason'],
                    );

                    Notification::make()->success()->title('Stock ajustado')->send();
                }),

            Action::make('transferir')
                ->label('Transferir entre sucursales')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->visible(fn (): bool => $this->branches()->count() >= 2)
                ->form([
                    Select::make('from')->label('Desde')
                        ->options(fn () => $this->branches()->pluck('name', 'id'))->required(),
                    Select::make('to')->label('Hacia')
                        ->options(fn () => $this->branches()->pluck('name', 'id'))->required(),
                    Select::make('product_id')->label('Producto')
                        ->options(fn () => $this->products()->pluck('name', 'id'))->required(),
                    TextInput::make('quantity')->label('Cantidad')->numeric()->minValue(1)->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        app(StockService::class)->transfer(
                            Filament::getTenant()->getKey(),
                            (int) $data['from'],
                            (int) $data['to'],
                            (int) $data['product_id'],
                            (int) $data['quantity'],
                        );

                        Notification::make()->success()->title('Transferencia realizada')->send();
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title('No se pudo transferir')->body($e->getMessage())->send();
                    }
                }),
        ];
    }
}
