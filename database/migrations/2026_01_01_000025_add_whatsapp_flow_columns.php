<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Último mensaje entrante del cliente: define la ventana de 24 horas.
            $table->timestamp('last_inbound_at')->nullable()->after('last_activity_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            // Marca de procesamiento para el debounce: agrupamos los entrantes
            // sin procesar de una conversación en un solo turno del agente.
            $table->timestamp('processed_at')->nullable()->after('raw_payload');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('last_inbound_at');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('processed_at');
        });
    }
};
