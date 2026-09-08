<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\User;
use App\Filament\Pages\PuntoDeVenta;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

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
}
