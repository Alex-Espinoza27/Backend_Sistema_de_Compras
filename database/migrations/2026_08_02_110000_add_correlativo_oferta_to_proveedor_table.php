<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OFER_CONSTANCIA_CODIGO usaba el OFER_ID crudo (05C hardening: no dice nada
 * al proveedor y se confundía con un documento distinto, p. ej. un RUC).
 * Mismo patrón que ENTI_CORRELATIVO_REQUERIMIENTO: contador propio por
 * proveedor con reinicio anual (NumeracionService::generarOferta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('PROVEEDOR', function (Blueprint $table) {
            $table->integer('PROV_CORRELATIVO_OFERTA')->default(0)->after('PROV_ORIGEN_DATO');
            $table->integer('PROV_CORRELATIVO_OFERTA_ANIO')->nullable()->after('PROV_CORRELATIVO_OFERTA');
        });
    }

    public function down(): void
    {
        Schema::table('PROVEEDOR', function (Blueprint $table) {
            $table->dropColumn(['PROV_CORRELATIVO_OFERTA', 'PROV_CORRELATIVO_OFERTA_ANIO']);
        });
    }
};
