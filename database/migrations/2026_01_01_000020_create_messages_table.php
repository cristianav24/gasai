<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role');                 // user | assistant | tool
            $table->text('content')->nullable();
            $table->string('wa_message_id')->nullable(); // ID de mensaje de WhatsApp
            $table->json('raw_payload')->nullable();     // Payload crudo del webhook
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('conversation_id');
            // Idempotencia: Meta reintenta webhooks. Sin esto el bot responde
            // dos veces al mismo mensaje entrante.
            $table->unique('wa_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
