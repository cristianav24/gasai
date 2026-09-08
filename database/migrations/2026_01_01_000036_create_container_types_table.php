<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tipos de envase retornable (bidón 20L, balón 10kg, etc.).
        Schema::create('container_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_types');
    }
};
