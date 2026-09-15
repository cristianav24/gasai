<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Inventario físico de envases del negocio, por tipo: llenos / vacíos / nuevos.
        Schema::create('container_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('container_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('full_count')->default(0);   // llenos, listos para entregar
            $table->unsignedInteger('empty_count')->default(0);  // vacíos, por recargar
            $table->unsignedInteger('new_count')->default(0);    // nuevos, sin vender
            $table->timestamps();
            $table->unique(['tenant_id', 'container_type_id']);
        });

        // Historial de movimientos del inventario de envases (un renglón por acción).
        Schema::create('container_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('container_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason'); // ingreso, retiro, llenar, entrega_recarga, entrega_nueva, ajuste
            $table->integer('full_delta')->default(0);
            $table->integer('empty_delta')->default(0);
            $table->integer('new_delta')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'container_type_id']);
        });

        // Idempotencia del efecto de la entrega sobre el inventario de envases.
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('container_stock_applied_at')->nullable()->after('containers_applied_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('container_stock_applied_at');
        });
        Schema::dropIfExists('container_stock_movements');
        Schema::dropIfExists('container_stocks');
    }
};
