<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('address_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            // Máquina de estados única: pendiente -> confirmado -> en_ruta -> entregado
            // (cancelado desde cualquier estado)
            $table->string('status')->default('pendiente');
            $table->string('channel')->default('whatsapp'); // whatsapp | playground | manual

            // Fecha + franja separadas: imposible guardar un pedido a las 00:00
            $table->date('scheduled_date')->nullable();
            $table->string('scheduled_slot')->nullable();   // manana | tarde | hora_exacta
            $table->time('scheduled_time')->nullable();     // obligatoria solo si slot = hora_exacta

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
