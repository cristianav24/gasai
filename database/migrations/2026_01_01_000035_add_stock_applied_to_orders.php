<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Marca de que ya se descontó el stock al entregar (idempotencia).
            $table->timestamp('stock_applied_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('stock_applied_at');
        });
    }
};
