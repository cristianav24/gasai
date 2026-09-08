<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un bot_config por tenant.
        Schema::create('bot_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('agent_name')->default('Asistente');
            $table->string('tone')->default('amable');   // Tono del agente
            $table->text('extra_instructions')->nullable();
            $table->text('welcome_message')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.30);
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_configs');
    }
};
