<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pedidos para la app del repartidor. El global scope de tenant ya acota por el
 * negocio del usuario autenticado; además filtramos por courier_id = el usuario.
 */
class OrderApiController extends Controller
{
    /** Pedidos asignados al repartidor autenticado que aún no se entregaron. */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('courier_id', $request->user()->id)
            ->whereIn('status', ['confirmado', 'en_ruta'])
            ->with(['customer', 'address', 'items'])
            ->orderBy('scheduled_date')
            ->get();

        return OrderResource::collection($orders)->response();
    }

    public function show(Request $request, int $order): JsonResponse
    {
        $model = Order::where('courier_id', $request->user()->id)
            ->with(['customer', 'address', 'items'])
            ->findOrFail($order);

        return (new OrderResource($model))->response();
    }

    /** Marca un pedido como entregado (solo si es del repartidor). */
    public function markDelivered(Request $request, int $order): JsonResponse
    {
        $model = Order::where('courier_id', $request->user()->id)->findOrFail($order);

        $model->update(['status' => 'entregado']);

        // Al entregar: descuenta stock y actualiza el saldo de envases (idempotente).
        app(\App\Services\Stock\StockService::class)->applyOrderDelivery($model);
        app(\App\Services\Containers\ContainerService::class)->applyOrderDelivery($model);

        return (new OrderResource($model->fresh(['customer', 'address', 'items'])))->response();
    }
}
