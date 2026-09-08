<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Turnos de caja: apertura, cierre y arqueo.
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Quien abrió

            $table->string('status')->default('abierta'); // abierta | cerrada
            $table->decimal('opening_amount', 10, 2)->default(0);  // Fondo inicial
            $table->decimal('closing_amount', 10, 2)->nullable();  // Efectivo contado al cerrar
            $table->decimal('expected_amount', 10, 2)->nullable(); // Efectivo esperado (calculado)
            $table->decimal('difference', 10, 2)->nullable();      // contado - esperado

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
