<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQU_NUMERO deja de ser digitado por el usuario (05C hardening): mismo
 * patrón que ENTI_CORRELATIVO_CONVOCATORIA (NumeracionService), contador
 * propio por entidad con reinicio anual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ENTIDAD', function (Blueprint $table) {
            $table->integer('ENTI_CORRELATIVO_REQUERIMIENTO')->default(0)->after('ENTI_CORRELATIVO_ANIO');
            $table->integer('ENTI_CORRELATIVO_REQUERIMIENTO_ANIO')->nullable()->after('ENTI_CORRELATIVO_REQUERIMIENTO');
        });
    }

    public function down(): void
    {
        Schema::table('ENTIDAD', function (Blueprint $table) {
            $table->dropColumn(['ENTI_CORRELATIVO_REQUERIMIENTO', 'ENTI_CORRELATIVO_REQUERIMIENTO_ANIO']);
        });
    }
};
