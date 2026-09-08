<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('product_name');
            $table->integer('quantity');
            // Los reportes de margen dependen de la diferencia entre estos dos.
            $table->decimal('unit_price_list', 10, 2);      // Precio de lista al momento
            $table->decimal('unit_price_charged', 10, 2);   // Precio realmente cobrado
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
