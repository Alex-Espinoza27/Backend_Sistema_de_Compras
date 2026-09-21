<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desactivar un rol en vez de eliminarlo (05C hardening, pedido explícitamente
 * por el usuario): un rol desactivado deja de ofrecerse para asignaciones
 * nuevas, pero no afecta a quienes ya lo tienen (decisión confirmada con el
 * usuario — sin tocar login/autenticación). Columna en minúscula, sigue el
 * estilo propio de la tabla `roles` de spatie/laravel-permission, no el
 * prefijo en mayúsculas de las tablas propias del proyecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('activo');
        });
    }
};
