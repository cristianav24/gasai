<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // WhatsApp usernames (desde jun 2026): el identificador puede ser un
        // BSUID (user_id) y no un número; y el contacto puede traer @username.
        Schema::table('customers', function (Blueprint $table) {
            $table->string('wa_user_id')->nullable()->after('phone');   // BSUID estable
            $table->string('username')->nullable()->after('wa_user_id'); // @username si lo tiene
            $table->index(['tenant_id', 'wa_user_id']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->string('wa_user_id')->nullable()->after('phone');   // identificador estable del contacto
            $table->string('contact_name')->nullable()->after('wa_user_id'); // nombre/nickname mostrado en WhatsApp
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['wa_user_id', 'username']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['wa_user_id', 'contact_name']);
        });
    }
};
