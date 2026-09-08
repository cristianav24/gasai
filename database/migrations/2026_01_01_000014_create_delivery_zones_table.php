<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('delivery_fee', 10, 2)->default(0); // Costo de envío
            $table->text('coverage')->nullable();               // Cobertura descrita en texto
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
