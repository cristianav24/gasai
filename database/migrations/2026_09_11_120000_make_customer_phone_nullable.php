<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los contactos con número oculto (WhatsApp usernames / BSUID) no tienen
 * teléfono: su identidad estable es wa_user_id. El teléfono deja de ser
 * obligatorio para poder registrarlos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('phone')->nullable(false)->change();
        });
    }
};
