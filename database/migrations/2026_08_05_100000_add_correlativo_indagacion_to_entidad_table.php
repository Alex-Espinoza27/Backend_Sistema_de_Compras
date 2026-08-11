<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Numeración por entidad del documento "Indagación de Mercado" (05C hardening,
 * pedido explícitamente por el usuario): mismo patrón que
 * ENTI_CORRELATIVO_REQUERIMIENTO (NumeracionService), contador propio con
 * reinicio anual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ENTIDAD', function (Blueprint $table) {
            $table->integer('ENTI_CORRELATIVO_INDAGACION')->default(0)->after('ENTI_CORRELATIVO_REQUERIMIENTO_ANIO');
            $table->integer('ENTI_CORRELATIVO_INDAGACION_ANIO')->nullable()->after('ENTI_CORRELATIVO_INDAGACION');
        });
    }

    public function down(): void
    {
        Schema::table('ENTIDAD', function (Blueprint $table) {
            $table->dropColumn(['ENTI_CORRELATIVO_INDAGACION', 'ENTI_CORRELATIVO_INDAGACION_ANIO']);
        });
    }
};
