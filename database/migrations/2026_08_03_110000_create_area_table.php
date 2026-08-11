<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nuevo módulo de Áreas (05C hardening, pedido explícitamente por el
 * usuario): cada entidad tiene su propio catálogo de áreas usuarias, en vez
 * de que el campo "Área" de un requerimiento sea texto libre. Se hace
 * backfill de los valores de texto ya existentes (uno por entidad+nombre
 * distinto) y se enlaza cada requerimiento a su área — `REQU_AREA_USUARIA`
 * se conserva como columna legada, sin usarse, por seguridad de datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('AREA', function (Blueprint $table) {
            $table->bigIncrements('AREA_ID');
            $table->unsignedBigInteger('AREA_ENTIDAD_ID');
            $table->string('AREA_NOMBRE', 150);

            MigrationColumns::auditoria($table, 'AREA');

            $table->foreign('AREA_ENTIDAD_ID')->references('ENTI_ID')->on('ENTIDAD')->cascadeOnDelete();
            $table->unique(['AREA_ENTIDAD_ID', 'AREA_NOMBRE']);
        });

        Schema::table('REQUERIMIENTO', function (Blueprint $table) {
            $table->unsignedBigInteger('REQU_AREA_ID')->nullable()->after('REQU_AREA_USUARIA');
            $table->foreign('REQU_AREA_ID')->references('AREA_ID')->on('AREA')->nullOnDelete();
        });

        DB::statement(<<<'SQL'
            INSERT INTO "AREA" ("AREA_ENTIDAD_ID", "AREA_NOMBRE")
            SELECT DISTINCT "REQU_ENTIDAD_ID", trim("REQU_AREA_USUARIA")
            FROM "REQUERIMIENTO"
            WHERE "REQU_AREA_USUARIA" IS NOT NULL AND trim("REQU_AREA_USUARIA") != ''
        SQL);

        DB::statement(<<<'SQL'
            UPDATE "REQUERIMIENTO" r
            SET "REQU_AREA_ID" = a."AREA_ID"
            FROM "AREA" a
            WHERE a."AREA_ENTIDAD_ID" = r."REQU_ENTIDAD_ID"
              AND a."AREA_NOMBRE" = trim(r."REQU_AREA_USUARIA")
        SQL);
    }

    public function down(): void
    {
        Schema::table('REQUERIMIENTO', function (Blueprint $table) {
            $table->dropForeign(['REQU_AREA_ID']);
            $table->dropColumn('REQU_AREA_ID');
        });

        Schema::dropIfExists('AREA');
    }
};
