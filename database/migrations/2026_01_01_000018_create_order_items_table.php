<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            // Congelamos nombre y precio: si el dueño renombra o borra el producto,
            // el historial del pedido no se corrompe.
            $table->string('product_name');
            $table->integer('quantity');
            $table->decimal('unit_price_list', 10, 2);        // Precio de lista al momento
            $table->decimal('unit_price_charged', 10, 2)->nullable(); // Se llena en el POS (Fase 7)
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
