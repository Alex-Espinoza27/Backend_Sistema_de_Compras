<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un requerimiento puede tener más de un rubro (05C hardening, pedido
 * explícitamente por el usuario) — `REQU_RUBRO_ID` (FK simple) pasa a ser
 * muchos-a-muchos vía esta tabla puente. Se hace backfill de los datos
 * existentes antes de dejar de escribir en la columna vieja (que se
 * conserva, sin usarse, por seguridad de datos — no se borra en caliente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('REQUERIMIENTO_RUBRO', function (Blueprint $table) {
            $table->bigIncrements('RERU_ID');
            $table->unsignedBigInteger('RERU_REQUERIMIENTO_ID');
            $table->unsignedBigInteger('RERU_RUBRO_ID');

            $table->foreign('RERU_REQUERIMIENTO_ID')->references('REQU_ID')->on('REQUERIMIENTO')->cascadeOnDelete();
            $table->foreign('RERU_RUBRO_ID')->references('RUBR_ID')->on('RUBRO')->cascadeOnDelete();
            $table->unique(['RERU_REQUERIMIENTO_ID', 'RERU_RUBRO_ID']);
        });

        DB::statement(<<<'SQL'
            INSERT INTO "REQUERIMIENTO_RUBRO" ("RERU_REQUERIMIENTO_ID", "RERU_RUBRO_ID")
            SELECT "REQU_ID", "REQU_RUBRO_ID" FROM "REQUERIMIENTO" WHERE "REQU_RUBRO_ID" IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('REQUERIMIENTO_RUBRO');
    }
};
