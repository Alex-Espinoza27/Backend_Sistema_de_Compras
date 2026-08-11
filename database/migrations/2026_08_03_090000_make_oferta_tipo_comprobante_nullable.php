<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Comprobante de pago" deja de pedirse para convocatorias de tipo BIEN (05C
 * hardening: decisión explícita del usuario, documentada como desviación
 * consciente de `00_CONTEXTO_TDR.md` §6, que lo pide siempre) — solo se
 * exige para SERVICIO (`OfertaStoreRequest`). La columna debe admitir null
 * para esos casos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('OFERTA', function (Blueprint $table) {
            $table->unsignedBigInteger('OFER_TIPO_COMPROBANTE_ID')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('OFERTA', function (Blueprint $table) {
            $table->unsignedBigInteger('OFER_TIPO_COMPROBANTE_ID')->nullable(false)->change();
        });
    }
};
