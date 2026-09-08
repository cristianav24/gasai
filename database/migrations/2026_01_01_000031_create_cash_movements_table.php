<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Movimientos de caja: entradas y salidas de efectivo distintas a ventas.
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type');            // entrada | salida
            $table->decimal('amount', 10, 2);
            $table->string('reason');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('cash_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
