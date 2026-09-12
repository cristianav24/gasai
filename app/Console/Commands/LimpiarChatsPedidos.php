<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Limpieza de datos de PRUEBA: elimina conversaciones (con sus mensajes) y
 * pedidos (con sus líneas). No toca clientes, productos, zonas, ventas ni caja.
 * Uso: php artisan gasai:limpiar-chats-pedidos --force [--tenant=slug]
 */
class LimpiarChatsPedidos extends Command
{
    protected $signature = 'gasai:limpiar-chats-pedidos {--tenant= : Slug del negocio (opcional; por defecto todos)} {--force}';

    protected $description = 'Elimina chats (conversaciones+mensajes) y pedidos (ordenes+items) de prueba.';

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

        if (! $this->option('force') && ! $this->confirm("Eliminar chats y pedidos {$ambito}?")) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        $scope = fn ($q) => $tenant ? $q->where('tenant_id', $tenant->id) : $q;

        DB::transaction(function () use ($scope): void {
            $msg = $scope(Message::withoutGlobalScopes())->delete();
            $items = $scope(OrderItem::withoutGlobalScopes())->delete();
            $ord = $scope(Order::withoutGlobalScopes())->delete();
            $conv = $scope(Conversation::withoutGlobalScopes()->withTrashed())->forceDelete();

            $this->info("Eliminados: {$conv} conversaciones, {$msg} mensajes, {$ord} pedidos, {$items} items.");
        });

        $this->info('Listo. Clientes, productos, zonas, ventas y caja se conservaron.');

        return self::SUCCESS;
    }
}
