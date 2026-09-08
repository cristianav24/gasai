<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // phone_number_id es único GLOBAL: el webhook resuelve el tenant con él.
            $table->string('phone_number_id')->unique();
            $table->string('waba_id')->nullable();
            $table->text('access_token')->nullable();   // cast 'encrypted' en el modelo
            $table->string('status')->default('disconnected'); // disconnected | connected | error
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
    }
};
