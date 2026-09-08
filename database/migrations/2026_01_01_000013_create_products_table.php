<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);              // Precio de lista (referencial)
            $table->string('unit')->default('unidad');    // bidón, balón, etc.
            $table->string('type')->default('venta');     // venta (nuevo) | recarga
            $table->boolean('requires_empty')->default(false); // recarga exige envase vacío
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
