<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // A qué tipo de envase corresponde el producto (si es retornable).
            $table->foreignId('container_type_id')->nullable()->after('requires_empty')
                ->constrained()->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            // Idempotencia del efecto sobre envases al entregar (aparte del stock).
            $table->timestamp('containers_applied_at')->nullable()->after('stock_applied_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('container_type_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('containers_applied_at');
        });
    }
};
