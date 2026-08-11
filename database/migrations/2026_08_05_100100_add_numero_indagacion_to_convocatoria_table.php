<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N.° del documento "Indagación de Mercado" (05C hardening, pedido
 * explícitamente por el usuario): se genera una sola vez, la primera vez que
 * se genera el PDF de resultados (ADJUDICADA o DESIERTA), y queda fijo para
 * siempre en la convocatoria — igual que CONV_NUMERO al publicar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('CONVOCATORIA', function (Blueprint $table) {
            $table->string('CONV_NUMERO_INDAGACION', 20)->nullable()->unique()->after('CONV_MOTIVO_CIERRE');
        });
    }

    public function down(): void
    {
        Schema::table('CONVOCATORIA', function (Blueprint $table) {
            $table->dropColumn('CONV_NUMERO_INDAGACION');
        });
    }
};
