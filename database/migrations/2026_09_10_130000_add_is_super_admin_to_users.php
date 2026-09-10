<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de super-administrador de la plataforma: acceso al panel de gestión de
 * negocios (/super). No es un rol dentro de un tenant, es a nivel plataforma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_super_admin')->default(false)->after('password');
        });

        // El fundador queda como super-admin (ajústalo si cambia el correo).
        DB::table('users')->where('email', 'francia24vs@gmail.com')->update(['is_super_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_super_admin');
        });
    }
};
