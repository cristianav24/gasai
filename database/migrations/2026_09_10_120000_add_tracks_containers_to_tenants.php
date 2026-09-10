<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interruptor de envases retornables por negocio. Apagado por defecto: solo los
 * negocios que prestan/dan en garantía sus envases (típicamente gas) lo activan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->boolean('tracks_containers')->default(false)->after('rubro');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('tracks_containers');
        });
    }
};
