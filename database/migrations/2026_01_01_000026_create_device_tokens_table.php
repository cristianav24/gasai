<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tokens de push de los dispositivos (app del repartidor/dueño).
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token');                 // Expo push token
            $table->string('platform')->nullable();  // ios | android
            $table->timestamps();

            $table->unique('token');
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
