<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Historial de envases: entregas, recargas, devoluciones, ajustes.
        Schema::create('container_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('container_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type');            // entrega_nueva | devolucion | ajuste
            $table->integer('delta');          // efecto sobre el saldo
            $table->string('reference')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['customer_id', 'container_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_movements');
    }
};
