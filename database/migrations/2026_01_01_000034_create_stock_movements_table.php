<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Historial de movimientos de stock (trazabilidad).
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // entrada | salida | ajuste | entrega | transferencia_entrada | transferencia_salida
            $table->string('type');
            $table->integer('quantity');        // Positivo o negativo según el efecto
            $table->string('reference')->nullable(); // ej. "pedido #12"
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['branch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
