<?php

namespace App\Console\Commands;

use App\Models\CashSession;
use App\Models\ContainerStock;
use App\Models\ContainerStockMovement;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Limpieza de datos de PRUEBA: elimina conversaciones (con sus mensajes) y
 * pedidos (con sus líneas). No toca productos, zonas, ventas ni caja.
 * Con --clientes borra también los clientes (y en cascada sus direcciones y
 * saldos de envases; las ventas se conservan, solo se desvinculan).
 * Con --todo hace un RESET completo para producción: además borra ventas, caja,
 * stock e inventario de bidones. Conserva SOLO la configuración (productos,
 * zonas, tipos de envase, config del bot/reparto, sucursales, usuarios).
 * Uso: php artisan gasai:limpiar-chats-pedidos --force [--clientes|--todo] [--tenant=slug]
 */
class LimpiarChatsPedidos extends Command
{
    protected $signature = 'gasai:limpiar-chats-pedidos {--tenant= : Slug del negocio (opcional; por defecto todos)} {--clientes : Elimina también los clientes (con sus direcciones y saldos de envases)} {--todo : Reset completo para producción: también ventas, caja, stock e inventario de bidones (conserva la configuración)} {--force}';

    protected $description = 'Elimina chats y pedidos de prueba. Con --clientes también los clientes; con --todo, reset completo (ventas, caja, stock, bidones) conservando la configuración.';

    public function handle(): int
    {
        $tenant = null;
        if ($slug = $this->option('tenant')) {
            $tenant = Tenant::where('slug', $slug)->first();
            if (! $tenant) {
                $this->error("No existe el negocio '{$slug}'.");

                return self::FAILURE;
            }
        }

        $ambito = $tenant ? "del negocio '{$tenant->name}'" : 'de TODOS los negocios';
        $todo = (bool) $this->option('todo');
        $conClientes = $todo || (bool) $this->option('clientes');
        $que = $todo ? 'TODO lo transaccional (chats, pedidos, clientes, ventas, caja, stock, bidones)'
            : ($conClientes ? 'chats, pedidos y CLIENTES' : 'chats y pedidos');

        if (! $this->option('force') && ! $this->confirm("Eliminar {$que} {$ambito}?")) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        $scope = fn ($q) => $tenant ? $q->where('tenant_id', $tenant->id) : $q;

        DB::transaction(function () use ($scope, $conClientes, $todo): void {
            $msg = $scope(Message::withoutGlobalScopes())->delete();
            $items = $scope(OrderItem::withoutGlobalScopes())->delete();
            $ord = $scope(Order::withoutGlobalScopes())->delete();
            $conv = $scope(Conversation::withoutGlobalScopes()->withTrashed())->forceDelete();

            $this->info("Eliminados: {$conv} conversaciones, {$msg} mensajes, {$ord} pedidos, {$items} items.");

            if ($todo) {
                // Ventas y caja (sale_items y cash_movements caen en cascada).
                $ventas = $scope(Sale::withoutGlobalScopes())->delete();
                $cajas = $scope(CashSession::withoutGlobalScopes())->delete();
                // Stock de productos e inventario de bidones (con sus movimientos).
                $scope(StockMovement::withoutGlobalScopes())->delete();
                $scope(StockLevel::withoutGlobalScopes())->delete();
                $scope(ContainerStockMovement::withoutGlobalScopes())->delete();
                $scope(ContainerStock::withoutGlobalScopes())->delete();

                $this->info("Eliminados: {$ventas} ventas, {$cajas} sesiones de caja, y el stock/inventario de bidones.");
            }

            if ($conClientes) {
                // Cascada de BD: al borrar el cliente se eliminan sus direcciones y
                // saldos/movimientos de envases.
                $cli = $scope(Customer::withoutGlobalScopes())->delete();
                $this->info("Eliminados: {$cli} clientes (con direcciones y saldos de envases).");
            }
        });

        $conserva = $todo
            ? 'Se conservó SOLO la configuración: productos, zonas, tipos de envase, config del bot/reparto, sucursales y usuarios.'
            : ($conClientes
                ? 'Productos, zonas, ventas y caja se conservaron.'
                : 'Clientes, productos, zonas, ventas y caja se conservaron.');
        $this->info("Listo. {$conserva}");

        return self::SUCCESS;
    }
}
