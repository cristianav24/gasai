<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zona geográfica del negocio, para acotar la geocodificación de direcciones que
 * el cliente escribe en texto (ciudad/región/país + un recuadro opcional).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('geo_city')->nullable()->after('timezone');
            $table->string('geo_region')->nullable()->after('geo_city');
            $table->string('geo_country')->nullable()->after('geo_region');
            $table->string('geo_viewbox')->nullable()->after('geo_country'); // "lon1,lat1,lon2,lat2"
        });

        // El negocio de agua ya en producción opera en Huancayo.
        DB::table('tenants')->where('slug', 'h2o-wanka')->update([
            'geo_city' => 'Huancayo',
            'geo_region' => 'Junín',
            'geo_country' => 'Perú',
            'geo_viewbox' => '-75.30,-11.95,-75.14,-12.13',
        ]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['geo_city', 'geo_region', 'geo_country', 'geo_viewbox']);
        });
    }
};
