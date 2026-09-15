<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            // Punto central de reparto (tu local).
            $table->decimal('delivery_center_lat', 10, 7)->nullable()->after('order_number_padding');
            $table->decimal('delivery_center_lng', 10, 7)->nullable()->after('delivery_center_lat');
            // Bandas de distancia: [{ "to_km": 2, "fee": 0 }, { "to_km": 4, "fee": 2 }, ...]
            $table->json('delivery_bands')->nullable()->after('delivery_center_lng');
            // Envío gratis si el subtotal alcanza este monto (null = desactivado).
            $table->decimal('delivery_free_over', 10, 2)->nullable()->after('delivery_bands');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['delivery_center_lat', 'delivery_center_lng', 'delivery_bands', 'delivery_free_over']);
        });
    }
};
