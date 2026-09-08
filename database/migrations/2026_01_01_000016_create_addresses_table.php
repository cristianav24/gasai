<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->text('address');
            $table->string('reference')->nullable();   // Referencia de ubicación
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
