<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Cajero
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();

            // Estado de la venta.
            $table->string('status')->default('en_espera'); // cobrada | en_espera | cancelada

            $table->decimal('subtotal', 10, 2)->default(0);      // Suma de precios cobrados
            $table->decimal('discount_total', 10, 2)->default(0); // Descuentos aplicados
            $table->decimal('total', 10, 2)->default(0);

            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
