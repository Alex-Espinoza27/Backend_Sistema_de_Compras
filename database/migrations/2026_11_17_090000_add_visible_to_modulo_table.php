<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulos ocultos: un módulo con MODU_VISIBLE = false no aparece en el menú de
 * nadie ni en la pantalla de Roles y Permisos (no se puede dar acceso a él).
 * Sus rutas siguen existiendo; los permisos que un rol ya tenía se conservan.
 *
 * Entidades se oculta: la plataforma trabaja con las entidades ya registradas
 * y los usuarios se asignan a ellas desde el módulo Usuarios. Para volver a
 * mostrarlo: UPDATE "MODULO" SET "MODU_VISIBLE" = true WHERE "MODU_CODIGO" = 'ENTIDADES';
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('MODULO', function (Blueprint $table) {
            $table->boolean('MODU_VISIBLE')->default(true)->after('MODU_ORDEN');
        });

        DB::table('MODULO')->where('MODU_CODIGO', 'ENTIDADES')->update(['MODU_VISIBLE' => false]);
    }

    public function down(): void
    {
        Schema::table('MODULO', function (Blueprint $table) {
            $table->dropColumn('MODU_VISIBLE');
        });
    }
};
