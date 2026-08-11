<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campo opcional para precisar la presentación/contenido de un ítem cuando
 * la unidad de medida no basta (ej. unidad "UNIDAD" pero el bien es
 * "botella de 500 ml") — 05C hardening, pedido explícitamente por el
 * usuario. Texto libre corto, disponible en cualquier ítem sin importar la
 * unidad de medida elegida (decisión confirmada, sin tocar el catálogo
 * UNIDAD_MEDIDA).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('REQUERIMIENTO_ITEM', function (Blueprint $table) {
            $table->string('REIT_PRESENTACION', 120)->nullable()->after('REIT_CANTIDAD');
        });
    }

    public function down(): void
    {
        Schema::table('REQUERIMIENTO_ITEM', function (Blueprint $table) {
            $table->dropColumn('REIT_PRESENTACION');
        });
    }
};
