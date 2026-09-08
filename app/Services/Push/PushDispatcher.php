<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use App\Models\Order;
use App\Models\Tenant;

/**
 * Decide a quién avisar por push ante eventos de pedidos y arma el mensaje.
 * El envío en sí lo hace el PushNotifier.
 */
class PushDispatcher
{
    public function __construct(private PushNotifier $notifier) {}

    /** Pedido nuevo: avisa a los dueños del negocio. */
    public function notifyNewOrder(Order $order): void
    {
        $tenant = Tenant::find($order->tenant_id);
        if (! $tenant) {
            return;
        }

        $ownerIds = $tenant->users()->wherePivot('role', 'owner')->pluck('users.id')->all();
        $tokens = $this->tokensFor($order->tenant_id, $ownerIds);

        $this->notifier->send(
            $tokens,
            'Nuevo pedido #' . $order->id,
            'Total S/ ' . number_format((float) $order->total, 2) . '. Revisa el despacho.',
            ['pedido_id' => $order->id],
        );
    }

    /** Pedido asignado: avisa al repartidor. */
    public function notifyCourierAssigned(Order $order): void
    {
        if (! $order->courier_id) {
            return;
        }

        $tokens = $this->tokensFor($order->tenant_id, [$order->courier_id]);

        $this->notifier->send(
            $tokens,
            'Nuevo reparto #' . $order->id,
            'Se te asignó un pedido. Ábrelo para ver los detalles.',
            ['pedido_id' => $order->id],
        );
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, string>
     */
    private function tokensFor(int $tenantId, array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return DeviceToken::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('user_id', $userIds)
            ->pluck('token')
            ->all();
    }
}
