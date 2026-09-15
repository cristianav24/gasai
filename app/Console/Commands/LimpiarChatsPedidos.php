<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Limpieza de datos de PRUEBA: elimina conversaciones (con sus mensajes) y
 * pedidos (con sus líneas). No toca productos, zonas, ventas ni caja.
 * Con --clientes borra también los clientes (y en cascada sus direcciones y
 * saldos de envases; las ventas se conservan, solo se desvinculan).
 * Uso: php artisan gasai:limpiar-chats-pedidos --force [--clientes] [--tenant=slug]
 */
class LimpiarChatsPedidos extends Command
{
    protected $signature = 'gasai:limpiar-chats-pedidos {--tenant= : Slug del negocio (opcional; por defecto todos)} {--clientes : Elimina también los clientes (con sus direcciones y saldos de envases)} {--force}';

    protected $description = 'Elimina chats (conversaciones+mensajes) y pedidos (ordenes+items) de prueba. Con --clientes, también los clientes.';

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
        $conClientes = (bool) $this->option('clientes');
        $que = $conClientes ? 'chats, pedidos y CLIENTES' : 'chats y pedidos';

        if (! $this->option('force') && ! $this->confirm("Eliminar {$que} {$ambito}?")) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        $scope = fn ($q) => $tenant ? $q->where('tenant_id', $tenant->id) : $q;

        DB::transaction(function () use ($scope, $conClientes): void {
            $msg = $scope(Message::withoutGlobalScopes())->delete();
            $items = $scope(OrderItem::withoutGlobalScopes())->delete();
            $ord = $scope(Order::withoutGlobalScopes())->delete();
            $conv = $scope(Conversation::withoutGlobalScopes()->withTrashed())->forceDelete();

            $this->info("Eliminados: {$conv} conversaciones, {$msg} mensajes, {$ord} pedidos, {$items} items.");

            if ($conClientes) {
                // Cascada de BD: al borrar el cliente se eliminan sus direcciones y
                // saldos/movimientos de envases; las ventas quedan con customer_id null.
                $cli = $scope(Customer::withoutGlobalScopes())->delete();
                $this->info("Eliminados: {$cli} clientes (con direcciones y saldos de envases).");
            }
        });

        $conserva = $conClientes
            ? 'Productos, zonas, ventas y caja se conservaron.'
            : 'Clientes, productos, zonas, ventas y caja se conservaron.';
        $this->info("Listo. {$conserva}");

        return self::SUCCESS;
    }
}
