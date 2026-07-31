<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;

/**
 * Columnas de auditoría comunes a toda tabla de dominio (01 §1.1). $timestamps
 * de Laravel está desactivado (02A §2.1); estas columnas las llena el trait
 * ConAuditoria, no el framework.
 */
class MigrationColumns
{
    public static function auditoria(Blueprint $table, string $prefix, bool $conSoftDelete = false): void
    {
        $table->boolean("{$prefix}_ACTIVO")->default(true);
        $table->timestampTz("{$prefix}_CREADO_EN")->nullable();
        $table->unsignedBigInteger("{$prefix}_CREADO_POR")->nullable();
        $table->timestampTz("{$prefix}_ACTUALIZADO_EN")->nullable();
        $table->unsignedBigInteger("{$prefix}_ACTUALIZADO_POR")->nullable();

        if ($conSoftDelete) {
            $table->timestampTz("{$prefix}_ELIMINADO_EN")->nullable();
        }

        $table->foreign("{$prefix}_CREADO_POR")->references('id')->on('users')->nullOnDelete();
        $table->foreign("{$prefix}_ACTUALIZADO_POR")->references('id')->on('users')->nullOnDelete();
    }
}
