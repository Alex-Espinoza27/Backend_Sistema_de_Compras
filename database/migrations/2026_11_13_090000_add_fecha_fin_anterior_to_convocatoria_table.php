<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Ampliar fecha fin" (05C hardening, pedido explícitamente por el usuario):
 * `CONV_FECHA_FIN` sigue siendo la fecha "activa" (la que ya leen todas las
 * consultas — vigencia, urgencia, exports, reportes, el job
 * `convocatorias:vencer`), así que ampliar el plazo simplemente la
 * sobrescribe con la nueva fecha; `CONV_FECHA_FIN_ANTERIOR` guarda el valor
 * que tenía justo antes de la última ampliación, solo para control interno
 * (nunca se expone en el portal público — ver ConvocatoriaService::ampliarFechaFin()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('CONVOCATORIA', function (Blueprint $table) {
            $table->timestampTz('CONV_FECHA_FIN_ANTERIOR')->nullable()->after('CONV_FECHA_FIN');
        });
    }

    public function down(): void
    {
        Schema::table('CONVOCATORIA', function (Blueprint $table) {
            $table->dropColumn('CONV_FECHA_FIN_ANTERIOR');
        });
    }
};
