<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // Nombre del negocio
            $table->string('slug')->unique();             // Subdominio por tenant
            $table->string('rubro')->default('agua');     // agua | gas | otro
            $table->string('currency', 3)->default('PEN');
            $table->string('timezone')->default('America/Lima');
            $table->json('business_hours')->nullable();   // Horario de atención
            // Onboarding (Fase 4) — sembrado desde ya para no migrar de nuevo
            $table->string('onboarding_step')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
