<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borrado lógico de conversaciones: se ocultan de la bandeja sin perder el
 * historial (mensajes y pedidos quedan). Un contacto que vuelva a escribir
 * inicia una conversación nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
