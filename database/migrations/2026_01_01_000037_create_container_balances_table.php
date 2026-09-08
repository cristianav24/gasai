<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Saldo de envases nuestros en poder de cada cliente.
        Schema::create('container_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('container_type_id')->constrained()->cascadeOnDelete();
            $table->integer('balance')->default(0); // + = el cliente tiene envases nuestros
            $table->timestamps();

            $table->unique(['customer_id', 'container_type_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_balances');
    }
};
