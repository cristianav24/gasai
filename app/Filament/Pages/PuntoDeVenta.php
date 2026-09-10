<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockLevel;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Punto de venta estilo caja: grilla de productos a la derecha, ticket al
 * centro. El precio es editable (acción humana); se guarda unit_price_list y
 * unit_price_charged por línea para los reportes de margen.
 */
class PuntoDeVenta extends Page
{
    protected string $view = 'filament.pages.punto-de-venta';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $title = 'Punto de Venta';

    protected static ?string $navigationLabel = 'Punto de Venta';

    protected static ?int $navigationSort = 5;

    /** POS a ancho completo (ocupa toda la pantalla, como una caja real). */
    public function getMaxContentWidth(): \Filament\Support\Enums\Width|string|null
    {
        return \Filament\Support\Enums\Width::Full;
    }

    /** Sin título grande arriba: más espacio para el POS. */
    public function getHeading(): string
    {
        return '';
    }

    public ?int $branchId = null;

    public ?int $customerId = null;

    public string $search = '';

    /** todo | venta | recarga */
    public string $categoryFilter = 'todo';

    /** @var array<int, array{product_id:int,name:string,unit:string,list:float,charged:float,qty:int}> */
    public array $cart = [];

    public string $note = '';

    public ?int $orderId = null;

    public bool $showPayment = false;

    public ?int $paymentMethodId = null;

    public bool $showNewCustomer = false;

    public string $newName = '';

    public string $newPhone = '';

    public function mount(): void
    {
        $this->branchId = Branch::where('active', true)->orderBy('id')->value('id')
            ?? Branch::orderBy('id')->value('id');

        // Cobro desde un pedido: precarga cliente e items.
        $orderParam = request()->query('order');
        if ($orderParam && ($order = Order::with('items')->find((int) $orderParam))) {
            $this->orderId = $order->id;
            $this->customerId = $order->customer_id;
            foreach ($order->items as $it) {
                $this->cart[] = [
                    'product_id' => (int) $it->product_id,
                    'name' => $it->product_name,
                    'unit' => optional($it->product)->unit ?? 'unidad',
                    'list' => (float) $it->unit_price_list,
                    // Precio vendido: el modificado en Despacho si existe, si no el de lista.
                    'charged' => (float) ($it->unit_price_charged ?? $it->unit_price_list),
                    'qty' => (int) $it->quantity,
                ];
            }
        }
    }

    // ---------- Datos para la vista ----------

    public function branches(): Collection
    {
        return Branch::orderBy('name')->get();
    }

    public function customers(): Collection
    {
        return Customer::orderBy('name')->get();
    }

    public function paymentMethods(): Collection
    {
        return PaymentMethod::where('active', true)->orderByDesc('is_cash')->orderBy('name')->get();
    }

    /** @return array<string, string> */
    public function categories(): array
    {
        $cats = ['todo' => 'Todo'];
        $tipos = Product::where('active', true)->distinct()->pluck('type');
        foreach ($tipos as $t) {
            $cats[$t] = match ($t) {
                'venta' => 'Venta (nuevo)',
                'recarga' => 'Recarga',
                default => ucfirst($t),
            };
        }

        return $cats;
    }

    public function products(): Collection
    {
        $stock = $this->branchId
            ? StockLevel::where('branch_id', $this->branchId)->pluck('quantity', 'product_id')
            : collect();

        return Product::where('active', true)
            ->when($this->categoryFilter !== 'todo', fn ($q) => $q->where('type', $this->categoryFilter))
            ->when($this->search !== '', fn ($q) => $q->where('name', 'ilike', '%' . $this->search . '%'))
            ->orderBy('name')
            ->get()
            ->map(function (Product $p) use ($stock): array {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'type' => $p->type,
                    'unit' => $p->unit,
                    'price' => (float) $p->price,
                    'stock' => (int) ($stock[$p->id] ?? 0),
                ];
            });
    }

    // ---------- Acciones del ticket ----------

    public function addProduct(int $productId): void
    {
        foreach ($this->cart as $i => $line) {
            if ($line['product_id'] === $productId) {
                $this->cart[$i]['qty']++;

                return;
            }
        }

        $p = Product::find($productId);
        if (! $p) {
            return;
        }

        $this->cart[] = [
            'product_id' => $p->id,
            'name' => $p->name,
            'unit' => $p->unit,
            'list' => (float) $p->price,
            'charged' => (float) $p->price,
            'qty' => 1,
        ];
    }

    public function incQty(int $index): void
    {
        if (isset($this->cart[$index])) {
            $this->cart[$index]['qty']++;
        }
    }

    public function decQty(int $index): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }
        if ($this->cart[$index]['qty'] <= 1) {
            $this->removeLine($index);

            return;
        }
        $this->cart[$index]['qty']--;
    }

    public function removeLine(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function cancelar(): void
    {
        $this->cart = [];
        $this->note = '';
        $this->customerId = null;
        $this->orderId = null;
        $this->showPayment = false;
    }

    // ---------- Alta rápida de cliente ----------

    public function openNewCustomer(): void
    {
        $this->newName = '';
        $this->newPhone = '';
        $this->showNewCustomer = true;
    }

    public function saveCustomer(): void
    {
        $phone = trim($this->newPhone);
        if (blank($phone)) {
            Notification::make()->danger()->title('El teléfono es obligatorio')->send();

            return;
        }

        $customer = Customer::updateOrCreate(
            ['tenant_id' => Filament::getTenant()->getKey(), 'phone' => $phone],
            ['name' => trim($this->newName) ?: null],
        );

        $this->customerId = $customer->id;
        $this->showNewCustomer = false;

        Notification::make()->success()->title('Cliente agregado')->send();
    }

    // ---------- Totales ----------

    public function subtotalLista(): float
    {
        return round(collect($this->cart)->sum(fn ($l) => $l['list'] * $l['qty']), 2);
    }

    public function totalCobrado(): float
    {
        return round(collect($this->cart)->sum(fn ($l) => (float) $l['charged'] * $l['qty']), 2);
    }

    public function descuentoTotal(): float
    {
        return round($this->subtotalLista() - $this->totalCobrado(), 2);
    }

    public function itemsCount(): int
    {
        return (int) collect($this->cart)->sum('qty');
    }

    // ---------- Cobro ----------

    public function openPayment(): void
    {
        if (empty($this->cart)) {
            Notification::make()->warning()->title('Agrega productos al ticket')->send();

            return;
        }
        $this->paymentMethodId = $this->paymentMethods()->first()?->id;
        $this->showPayment = true;
    }

    public function closePayment(): void
    {
        $this->showPayment = false;
    }

    public function cobrar(): void
    {
        if (blank($this->paymentMethodId)) {
            Notification::make()->danger()->title('Elige un método de pago')->send();

            return;
        }
        $this->persist('cobrada');
    }

    public function enEspera(): void
    {
        if (empty($this->cart)) {
            Notification::make()->warning()->title('Agrega productos al ticket')->send();

            return;
        }
        $this->persist('en_espera');
    }

    private function persist(string $status): void
    {
        $branch = $this->branchId ? Branch::find($this->branchId) : null;
        $listTotal = $this->subtotalLista();
        $chargedTotal = $this->totalCobrado();

        // Si es efectivo y hay caja abierta, vincula la venta al turno.
        $cashSessionId = null;
        if ($status === 'cobrada' && $this->paymentMethodId) {
            $method = PaymentMethod::find($this->paymentMethodId);
            if ($method?->is_cash && $branch) {
                $cashSessionId = \App\Models\CashSession::where('branch_id', $branch->id)
                    ->where('status', 'abierta')->value('id');
            }
        }

        $cart = $this->cart;

        $sale = DB::transaction(function () use ($status, $branch, $listTotal, $chargedTotal, $cashSessionId, $cart): Sale {
            $sale = Sale::create([
                'tenant_id' => Filament::getTenant()->getKey(),
                'branch_id' => $branch?->id,
                'order_id' => $this->orderId,
                'customer_id' => $this->customerId,
                'user_id' => auth()->id(),
                'payment_method_id' => $status === 'cobrada' ? $this->paymentMethodId : null,
                'cash_session_id' => $cashSessionId,
                'status' => $status,
                'subtotal' => round($listTotal, 2),
                'discount_total' => round($listTotal - $chargedTotal, 2),
                'total' => round($chargedTotal, 2),
                'notes' => $this->note ?: null,
                'paid_at' => $status === 'cobrada' ? now() : null,
            ]);

            foreach ($cart as $line) {
                $sale->items()->create([
                    'tenant_id' => $sale->tenant_id,
                    'product_id' => $line['product_id'],
                    'product_name' => $line['name'],
                    'quantity' => (int) $line['qty'],
                    'unit_price_list' => (float) $line['list'],
                    'unit_price_charged' => (float) $line['charged'],
                ]);
            }

            return $sale;
        });

        Notification::make()->success()
            ->title($status === 'cobrada' ? 'Venta cobrada' : 'Venta en espera')
            ->body('Total: S/ ' . number_format((float) $sale->total, 2))
            ->send();

        $this->cancelar();
    }
}
