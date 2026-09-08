<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sucursales / almacenes. Capa distinta del multi-tenant:
        // un tenant tiene 1+ sucursales; pedidos, ventas, stock y caja
        // pertenecen a una sucursal.
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');                 // ej. "Central"
            $table->string('address')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
