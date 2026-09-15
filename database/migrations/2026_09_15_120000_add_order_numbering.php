<?php

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configuración por negocio: desde qué número empiezan los pedidos y con
        // cuántos dígitos se muestran (relleno con ceros).
        Schema::table('tenants', function (Blueprint $table): void {
            $table->unsignedInteger('order_number_start')->default(1)->after('geo_viewbox');
            $table->unsignedTinyInteger('order_number_padding')->default(5)->after('order_number_start');
        });

        // Número propio del pedido, secuencial por negocio (independiente del id).
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedInteger('number')->nullable()->after('id');
            $table->index(['tenant_id', 'number']);
        });

        // Backfill: numera los pedidos existentes por negocio, respetando el
        // número inicial configurado y el orden de creación.
        foreach (Tenant::query()->pluck('id') as $tenantId) {
            $start = 1;
            $n = $start;
            $orders = Order::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->orderBy('id')
                ->get(['id']);
            foreach ($orders as $order) {
                Order::withoutGlobalScopes()->whereKey($order->id)->update(['number' => $n]);
                $n++;
            }
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'number']);
            $table->dropColumn('number');
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['order_number_start', 'order_number_padding']);
        });
    }
};
