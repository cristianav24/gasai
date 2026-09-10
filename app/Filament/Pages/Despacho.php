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
            ->with(['customer', 'courier', 'items'])
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
        $this->noItems = [['product_id' => null, 'qty' => 1]];
        $this->showNewOrder = true;
    }

    public function closeNewOrder(): void
    {
        $this->showNewOrder = false;
    }

    public function addOrderItem(): void
    {
        $this->noItems[] = ['product_id' => null, 'qty' => 1];
    }

    public function removeOrderItem(int $index): void
    {
        unset($this->noItems[$index]);
        $this->noItems = array_values($this->noItems);
        if (empty($this->noItems)) {
            $this->noItems = [['product_id' => null, 'qty' => 1]];
        }
    }

    /** Items del formulario en el formato que espera OrderPricing. */
    private function normalizedItems(): array
    {
        return collect($this->noItems)
            ->filter(fn ($l) => ! empty($l['product_id']) && (int) $l['qty'] > 0)
            ->map(fn ($l) => ['producto_id' => (int) $l['product_id'], 'cantidad' => (int) $l['qty']])
            ->values()
            ->all();
    }

    /** Cálculo en vivo para la vista previa del total. */
    public function newOrderCalc(): array
    {
        return app(OrderPricing::class)->calcular(
            $this->normalizedItems(),
            $this->noZoneId ? (int) $this->noZoneId : null,
        );
    }

    private function resolveCustomer(): ?Customer
    {
        $tenantId = Filament::getTenant()->getKey();

        if ($this->customerMode === 'existente' && $this->noCustomerId) {
            return Customer::find($this->noCustomerId);
        }

        $name = trim($this->noName);
        $phone = trim($this->noPhone);

        if ($name === '' && $phone === '') {
            return null;
        }

        if ($phone !== '') {
            return Customer::updateOrCreate(
                ['tenant_id' => $tenantId, 'phone' => $phone],
                ['name' => $name ?: null],
            );
        }

        return Customer::create(['tenant_id' => $tenantId, 'name' => $name]);
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

        $customer = $this->resolveCustomer();
        if (! $customer) {
            Notification::make()->danger()
                ->title('Indica el cliente')
                ->body('Elige uno existente o escribe al menos su nombre.')->send();

            return;
        }

        $calc = $pricing->calcular($items, $this->noZoneId ? (int) $this->noZoneId : null);
        if (! empty($calc['errores'])) {
            Notification::make()->danger()->title('Revisa el pedido')->body(implode(' ', $calc['errores']))->send();

            return;
        }

        $branch = Branch::where('active', true)->orderBy('id')->first() ?? Branch::orderBy('id')->first();
        if (! $branch) {
            Notification::make()->danger()->title('No hay una sucursal configurada')->send();

            return;
        }

        $order = DB::transaction(function () use ($customer, $branch, $calc): Order {
            $order = Order::create([
                'tenant_id' => Filament::getTenant()->getKey(),
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'delivery_zone_id' => $this->noZoneId ? (int) $this->noZoneId : null,
                'subtotal' => $calc['subtotal'],
                'delivery_fee' => $calc['costo_envio'],
                'total' => $calc['total'],
                'status' => 'pendiente',
                'channel' => 'manual',
                'scheduled_date' => $this->noDate,
                'scheduled_slot' => $this->noSlot,
                'scheduled_time' => $this->noSlot === 'hora_exacta' ? trim($this->noTime) : null,
                'notes' => trim($this->noNotes) ?: null,
            ]);

            foreach ($calc['lineas'] as $linea) {
                $order->items()->create([
                    'tenant_id' => $order->tenant_id,
                    'product_id' => $linea['producto_id'],
                    'product_name' => $linea['nombre'],
                    'quantity' => $linea['cantidad'],
                    'unit_price_list' => $linea['precio_unitario'],
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
}
