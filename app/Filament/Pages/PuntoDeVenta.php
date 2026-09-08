<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * Punto de venta: venta de mostrador o cobro desde un pedido. El precio unitario
 * es editable (acción humana), pero se guarda siempre unit_price_list y
 * unit_price_charged por línea para los reportes de margen. El LLM nunca edita precios.
 */
class PuntoDeVenta extends Page
{
    protected string $view = 'filament.pages.punto-de-venta';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $title = 'Punto de Venta';

    protected static ?string $navigationLabel = 'Punto de Venta';

    protected static ?int $navigationSort = 5;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?int $orderId = null;

    public function mount(): void
    {
        $initial = [
            'customer_id' => null,
            'items' => [],
            'payment_method_id' => null,
            'notes' => null,
        ];

        // Cobro desde un pedido: precarga cliente e items del pedido.
        $orderParam = request()->query('order');
        if ($orderParam && ($order = Order::with('items')->find((int) $orderParam))) {
            $this->orderId = $order->id;
            $initial['customer_id'] = $order->customer_id; // Nunca cae en "Mostrador" si el pedido tenía cliente.
            $initial['items'] = $order->items->map(fn ($it): array => [
                'product_id' => $it->product_id,
                'product_name' => $it->product_name,
                'quantity' => $it->quantity,
                'unit_price_list' => (float) $it->unit_price_list,
                'unit_price_charged' => (float) $it->unit_price_list,
                'note' => null,
            ])->all();
        }

        $this->form->fill($initial);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->label('Cliente')
                    ->placeholder('Mostrador (sin cliente)')
                    ->options(fn (): array => Customer::query()
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Customer $c): array => [
                            $c->id => ($c->name ?? 'Sin nombre') . ' — ' . $c->phone,
                        ])->all())
                    ->searchable(),

                Repeater::make('items')
                    ->label('Productos')
                    ->schema([
                        Select::make('product_id')
                            ->label('Producto')
                            ->options(fn (): array => Product::where('active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $product = Product::find($state);
                                if ($product) {
                                    $set('product_name', $product->name);
                                    $set('unit_price_list', (float) $product->price);
                                    $set('unit_price_charged', (float) $product->price);
                                }
                            }),

                        TextInput::make('quantity')
                            ->label('Cantidad')
                            ->numeric()->minValue(1)->default(1)->required(),

                        TextInput::make('unit_price_charged')
                            ->label('Precio a cobrar')
                            ->numeric()->minValue(0)->required()->prefix('S/')
                            ->helperText(fn (Get $get): string => 'Lista: S/ ' . number_format((float) ($get('unit_price_list') ?? 0), 2)),

                        TextInput::make('note')->label('Nota')->maxLength(255),

                        // Ocultos: se congelan al elegir el producto.
                        \Filament\Forms\Components\Hidden::make('product_name'),
                        \Filament\Forms\Components\Hidden::make('unit_price_list'),
                    ])
                    ->columns(2)
                    ->addActionLabel('Agregar producto')
                    ->live()
                    ->default([]),

                Select::make('payment_method_id')
                    ->label('Método de pago')
                    ->options(fn (): array => PaymentMethod::where('active', true)->pluck('name', 'id')->all()),

                Textarea::make('notes')->label('Nota de la venta')->rows(2),
            ])
            ->statePath('data');
    }

    /** Total en vivo (calculado por el servidor, no por el LLM). */
    public function total(): float
    {
        $total = 0.0;
        foreach ($this->data['items'] ?? [] as $item) {
            $total += (float) ($item['unit_price_charged'] ?? 0) * (int) ($item['quantity'] ?? 0);
        }

        return round($total, 2);
    }

    public function cobrar(): void
    {
        $this->persist('cobrada');
    }

    public function guardarEnEspera(): void
    {
        $this->persist('en_espera');
    }

    private function persist(string $status): void
    {
        $data = $this->form->getState();

        $items = collect($data['items'] ?? [])->filter(fn ($i) => ! blank($i['product_id'] ?? null));

        if ($items->isEmpty()) {
            Notification::make()->danger()->title('Agrega al menos un producto')->send();

            return;
        }

        if ($status === 'cobrada' && blank($data['payment_method_id'] ?? null)) {
            Notification::make()->danger()->title('Elige un método de pago para cobrar')->send();

            return;
        }

        $branch = Branch::where('active', true)->orderBy('id')->first() ?? Branch::orderBy('id')->first();

        $listTotal = 0.0;
        $chargedTotal = 0.0;
        foreach ($items as $it) {
            $qty = (int) $it['quantity'];
            $listTotal += (float) $it['unit_price_list'] * $qty;
            $chargedTotal += (float) $it['unit_price_charged'] * $qty;
        }

        // Si se cobra en efectivo y hay una caja abierta en la sucursal, la venta
        // se vincula a ese turno para el arqueo.
        $cashSessionId = null;
        if ($status === 'cobrada' && ! blank($data['payment_method_id'] ?? null)) {
            $method = \App\Models\PaymentMethod::find($data['payment_method_id']);
            if ($method?->is_cash && $branch) {
                $cashSessionId = \App\Models\CashSession::where('branch_id', $branch->id)
                    ->where('status', 'abierta')->value('id');
            }
        }

        $sale = DB::transaction(function () use ($data, $items, $status, $branch, $listTotal, $chargedTotal, $cashSessionId): Sale {
            $sale = Sale::create([
                'tenant_id' => Filament::getTenant()->getKey(),
                'branch_id' => $branch?->id,
                'order_id' => $this->orderId,
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => auth()->id(),
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'cash_session_id' => $cashSessionId,
                'status' => $status,
                'subtotal' => round($listTotal, 2),
                'discount_total' => round($listTotal - $chargedTotal, 2),
                'total' => round($chargedTotal, 2),
                'notes' => $data['notes'] ?? null,
                'paid_at' => $status === 'cobrada' ? now() : null,
            ]);

            foreach ($items as $it) {
                $sale->items()->create([
                    'tenant_id' => $sale->tenant_id,
                    'product_id' => $it['product_id'],
                    'product_name' => $it['product_name'] ?? Product::find($it['product_id'])?->name ?? 'Producto',
                    'quantity' => (int) $it['quantity'],
                    'unit_price_list' => (float) $it['unit_price_list'],
                    'unit_price_charged' => (float) $it['unit_price_charged'],
                    'note' => $it['note'] ?? null,
                ]);
            }

            return $sale;
        });

        Notification::make()->success()
            ->title($status === 'cobrada' ? 'Venta cobrada' : 'Venta guardada en espera')
            ->body('Total: S/ ' . number_format((float) $sale->total, 2))
            ->send();

        // Reiniciamos para la siguiente venta.
        $this->orderId = null;
        $this->form->fill(['customer_id' => null, 'items' => [], 'payment_method_id' => null, 'notes' => null]);
    }
}
