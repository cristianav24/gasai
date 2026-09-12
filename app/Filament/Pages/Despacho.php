<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Filament\Pages\PuntoDeVenta;
use App\Services\Agent\OrderPricing;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tablero de despacho: pedidos en columnas por estado, con asignación de
 * repartidor y avance de estado. El flujo es pendiente → confirmado → en_ruta
 * → entregado (cancelado desde cualquier estado).
 */
class Despacho extends Page
{
    protected string $view = 'filament.pages.despacho';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $title = 'Despacho';

    protected static ?string $navigationLabel = 'Despacho';

    protected static ?int $navigationSort = 6;

    /** Columnas visibles del tablero (sin "cancelado"). */
    public function columns(): array
    {
        return Order::FLOW;
    }

    public function label(string $status): string
    {
        return Order::LABELS[$status] ?? $status;
    }

    /**
     * Pedidos activos agrupados por estado (los cancelados no se muestran).
     *
     * @return Collection<string, \Illuminate\Support\Collection<int, Order>>
     */
    public function ordersByStatus(): Collection
    {
        $orders = Order::query()
            ->whereIn('status', Order::FLOW)
            ->with(['customer', 'courier', 'items', 'address'])
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->get();

        return $orders->groupBy('status');
    }

    /** Repartidores del tenant (rol courier) para el selector de asignación. */
    public function couriers(): Collection
    {
        return Filament::getTenant()
            ->users()
            ->wherePivot('role', 'courier')
            ->get(['users.id', 'users.name']);
    }

    public function assignCourier(int $orderId, ?int $courierId): void
    {
        $order = Order::findOrFail($orderId);
        $order->update(['courier_id' => $courierId ?: null]);

        // Avisamos al repartidor recién asignado.
        if ($order->courier_id) {
            app(\App\Services\Push\PushDispatcher::class)->notifyCourierAssigned($order);
        }

        Notification::make()->success()->title('Repartidor actualizado')->send();
    }

    public function advance(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $next = $order->nextStatus();

        if ($next === null) {
            return;
        }

        // No se puede entregar sin stock suficiente.
        if ($next === 'entregado' && $this->blockedByStock($order)) {
            return;
        }

        $order->update(['status' => $next]);

        // Al entregar: descuenta stock y actualiza el saldo de envases (idempotente).
        if ($next === 'entregado') {
            app(\App\Services\Stock\StockService::class)->applyOrderDelivery($order);
            app(\App\Services\Containers\ContainerService::class)->applyOrderDelivery($order);
        }

        Notification::make()->success()->title('Pedido: ' . $this->label($next))->send();
    }

    public function cancel(int $orderId): void
    {
        Order::findOrFail($orderId)->update(['status' => 'cancelado']);

        Notification::make()->warning()->title('Pedido cancelado')->send();
    }

    /** URL del POS con el pedido precargado (cliente e items). */
    public function cobrarUrl(int $orderId): string
    {
        return PuntoDeVenta::getUrl() . '?order=' . $orderId;
    }

    // ---------- Alta de pedido manual (dictado por teléfono, mostrador, etc.) ----------

    /** Franjas horarias válidas (mismas que usa el agente). */
    public const SLOTS = ['manana' => 'Mañana', 'tarde' => 'Tarde', 'hora_exacta' => 'Hora exacta'];

    public bool $showNewOrder = false;

    /** 'nuevo' escribe nombre/teléfono; 'existente' elige de la lista. */
    public string $customerMode = 'nuevo';

    public ?int $noCustomerId = null;

    public string $noName = '';

    public string $noPhone = '';

    public ?int $noZoneId = null;

    public string $noDate = '';

    public string $noSlot = 'manana';

    public string $noTime = '';

    public string $noNotes = '';

    /** @var array<int, array{product_id: ?int, qty: int}> */
    public array $noItems = [];

    /** @return Collection<int, Product> */
    public function productsList(): Collection
    {
        return Product::where('active', true)->orderBy('name')->get(['id', 'name', 'price', 'unit']);
    }

    /** @return Collection<int, DeliveryZone> */
    public function zonesList(): Collection
    {
        return DeliveryZone::where('active', true)->orderBy('name')->get(['id', 'name', 'delivery_fee']);
    }

    /** @return Collection<int, Customer> */
    public function customersList(): Collection
    {
        return Customer::orderBy('name')->get();
    }

    public function openNewOrder(): void
    {
        $this->reset(['noCustomerId', 'noName', 'noPhone', 'noZoneId', 'noTime', 'noNotes']);
        $this->customerMode = 'nuevo';
        $this->noDate = now()->format('Y-m-d');
        $this->noSlot = 'manana';
        $this->noItems = [['product_id' => null, 'qty' => 1, 'price' => null]];
        $this->showNewOrder = true;
    }

    public function closeNewOrder(): void
    {
        $this->showNewOrder = false;
    }

    public function addOrderItem(): void
    {
        $this->noItems[] = ['product_id' => null, 'qty' => 1, 'price' => null];
    }

    public function removeOrderItem(int $index): void
    {
        unset($this->noItems[$index]);
        $this->noItems = array_values($this->noItems);
        if (empty($this->noItems)) {
            $this->noItems = [['product_id' => null, 'qty' => 1, 'price' => null]];
        }
    }

    /** Items del formulario en el formato que espera OrderPricing (+ override de precio). */
    private function normalizedItems(): array
    {
        return collect($this->noItems)
            ->filter(fn ($l) => ! empty($l['product_id']) && (int) $l['qty'] > 0)
            ->map(fn ($l) => [
                'producto_id' => (int) $l['product_id'],
                'cantidad' => (int) $l['qty'],
                'precio' => $l['price'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Desglose del pedido usando el precio VENDIDO (override por línea si existe,
     * si no el de lista). El precio de lista se conserva aparte para el margen.
     */
    public function newOrderCalc(?OrderPricing $pricing = null): array
    {
        $pricing ??= app(OrderPricing::class);
        $base = $this->normalizedItems();
        $calc = $pricing->calcular($base, $this->noZoneId ? (int) $this->noZoneId : null);

        $lineas = [];
        $subtotal = 0.0;
        foreach ($calc['lineas'] as $k => $ln) {
            $override = $base[$k]['precio'] ?? null;
            $charged = (is_numeric($override) && (float) $override >= 0)
                ? round((float) $override, 2)
                : (float) $ln['precio_unitario'];
            $subtotal += $charged * $ln['cantidad'];
            $lineas[] = $ln + ['charged' => $charged];
        }
        $subtotal = round($subtotal, 2);

        return [
            'errores' => $calc['errores'],
            'lineas' => $lineas,
            'subtotal' => $subtotal,
            'costo_envio' => (float) $calc['costo_envio'],
            'total' => round($subtotal + (float) $calc['costo_envio'], 2),
        ];
    }

    /**
     * Cliente del pedido. Solo crea un Customer cuando hay teléfono (es la clave
     * del contacto). Un cliente nuevo con solo nombre no se registra: el pedido
     * queda sin cliente y el nombre se guarda en las notas (lo maneja createOrder).
     */
    private function resolveCustomer(): ?Customer
    {
        $tenantId = Filament::getTenant()->getKey();

        if ($this->customerMode === 'existente' && $this->noCustomerId) {
            return Customer::find($this->noCustomerId);
        }

        $phone = trim($this->noPhone);

        if ($this->customerMode === 'nuevo' && $phone !== '') {
            return Customer::updateOrCreate(
                ['tenant_id' => $tenantId, 'phone' => $phone],
                ['name' => trim($this->noName) ?: null],
            );
        }

        return null;
    }

    public function createOrder(OrderPricing $pricing): void
    {
        $items = $this->normalizedItems();
        if (empty($items)) {
            Notification::make()->danger()->title('Agrega al menos un producto')->send();

            return;
        }

        if (trim($this->noDate) === '') {
            Notification::make()->danger()->title('Indica la fecha de entrega')->send();

            return;
        }

        if ($this->noSlot === 'hora_exacta' && trim($this->noTime) === '') {
            Notification::make()->danger()->title('Elegiste hora exacta: indica la hora')->send();

            return;
        }

        // Cliente: existente o nuevo-con-teléfono. Si escribieron solo un nombre,
        // el pedido va sin cliente registrado y el nombre se anota en las notas.
        $customer = $this->resolveCustomer();
        $notes = trim($this->noNotes);

        if (! $customer) {
            $name = trim($this->noName);
            if ($this->customerMode === 'existente' || $name === '') {
                Notification::make()->danger()
                    ->title('Indica el cliente')
                    ->body('Elige uno existente o escribe al menos su nombre.')->send();

                return;
            }
            $notes = trim("Cliente: {$name}\n{$notes}");
        }

        $calc = $this->newOrderCalc($pricing);
        if (! empty($calc['errores'])) {
            Notification::make()->danger()->title('Revisa el pedido')->body(implode(' ', $calc['errores']))->send();

            return;
        }

        $branch = Branch::where('active', true)->orderBy('id')->first() ?? Branch::orderBy('id')->first();
        if (! $branch) {
            Notification::make()->danger()->title('No hay una sucursal configurada')->send();

            return;
        }

        $order = DB::transaction(function () use ($customer, $branch, $calc, $notes): Order {
            $order = Order::create([
                'tenant_id' => Filament::getTenant()->getKey(),
                'branch_id' => $branch->id,
                'customer_id' => $customer?->id,
                'delivery_zone_id' => $this->noZoneId ? (int) $this->noZoneId : null,
                'subtotal' => $calc['subtotal'],
                'delivery_fee' => $calc['costo_envio'],
                'total' => $calc['total'],
                'status' => 'pendiente',
                'channel' => 'manual',
                'scheduled_date' => $this->noDate,
                'scheduled_slot' => $this->noSlot,
                'scheduled_time' => $this->noSlot === 'hora_exacta' ? trim($this->noTime) : null,
                'notes' => $notes ?: null,
            ]);

            foreach ($calc['lineas'] as $linea) {
                $order->items()->create([
                    'tenant_id' => $order->tenant_id,
                    'product_id' => $linea['producto_id'],
                    'product_name' => $linea['nombre'],
                    'quantity' => $linea['cantidad'],
                    'unit_price_list' => $linea['precio_unitario'],
                    'unit_price_charged' => $linea['charged'],
                ]);
            }

            return $order;
        });

        $this->showNewOrder = false;

        Notification::make()->success()
            ->title("Pedido #{$order->id} creado")
            ->body('Total: S/ ' . number_format((float) $order->total, 2) . ' · queda en Pendiente.')
            ->send();
    }

    // ---------- Entregar y cobrar ----------

    /**
     * Marca el pedido como entregado (descuenta stock y envases, idempotente) y
     * redirige al POS con el cliente, los productos y el precio vendido ya cargados.
     */
    public function deliverAndCharge(int $orderId)
    {
        $order = Order::findOrFail($orderId);

        if ($order->status !== 'entregado') {
            if ($this->blockedByStock($order)) {
                return null;
            }
            $order->update(['status' => 'entregado']);
            app(\App\Services\Stock\StockService::class)->applyOrderDelivery($order);
            app(\App\Services\Containers\ContainerService::class)->applyOrderDelivery($order);
        }

        return redirect($this->cobrarUrl($orderId));
    }

    /**
     * Bloquea la entrega si falta stock. Devuelve true (y avisa) cuando no se
     * puede entregar; false cuando hay existencias suficientes.
     */
    private function blockedByStock(Order $order): bool
    {
        $short = app(\App\Services\Stock\StockService::class)->shortfallsFor($order);

        if (empty($short)) {
            return false;
        }

        Notification::make()->danger()
            ->title('No hay stock suficiente para entregar')
            ->body(implode(', ', $short) . '. Carga inventario en Inventario → “Ajustar stock”.')
            ->persistent()
            ->send();

        return true;
    }

    // ---------- Editar precios de un pedido existente ----------

    public bool $showEdit = false;

    public ?int $editOrderId = null;

    /** @var array<int, array{id: int, name: string, qty: int, list: float, charged: float}> */
    public array $editItems = [];

    public function openEdit(int $orderId): void
    {
        $order = Order::with('items')->findOrFail($orderId);

        $this->editOrderId = $order->id;
        $this->editItems = $order->items->map(fn ($it): array => [
            'id' => $it->id,
            'name' => $it->product_name,
            'qty' => (int) $it->quantity,
            'list' => (float) $it->unit_price_list,
            'charged' => (float) ($it->unit_price_charged ?? $it->unit_price_list),
        ])->all();

        $this->showEdit = true;
    }

    public function closeEdit(): void
    {
        $this->showEdit = false;
    }

    /** Total en vivo del modal de edición (precio vendido + envío del pedido). */
    public function editTotal(): float
    {
        $order = $this->editOrderId ? Order::find($this->editOrderId) : null;
        $fee = $order ? (float) $order->delivery_fee : 0.0;

        $sub = collect($this->editItems)
            ->sum(fn ($r) => round((float) $r['charged'], 2) * (int) $r['qty']);

        return round($sub + $fee, 2);
    }

    public function saveEdit(): void
    {
        $order = Order::with('items')->findOrFail($this->editOrderId);

        $subtotal = 0.0;
        DB::transaction(function () use ($order, &$subtotal): void {
            foreach ($this->editItems as $row) {
                $item = $order->items->firstWhere('id', $row['id']);
                if (! $item) {
                    continue;
                }
                $charged = round((float) $row['charged'], 2);
                $item->update(['unit_price_charged' => $charged]);
                $subtotal += $charged * $item->quantity;
            }

            $order->update([
                'subtotal' => round($subtotal, 2),
                'total' => round($subtotal + (float) $order->delivery_fee, 2),
            ]);
        });

        $this->showEdit = false;

        Notification::make()->success()
            ->title('Precios actualizados')
            ->body('Nuevo total: S/ ' . number_format($order->fresh()->total, 2))
            ->send();
    }
}
