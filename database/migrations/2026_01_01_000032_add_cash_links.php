<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            // Marca los métodos que son efectivo (cuentan para el arqueo).
            $table->boolean('is_cash')->default(false)->after('name');
        });

        Schema::table('sales', function (Blueprint $table) {
            // Venta en efectivo vinculada al turno de caja abierto al cobrar.
            $table->foreignId('cash_session_id')->nullable()->after('payment_method_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_session_id');
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('is_cash');
        });
    }
};
