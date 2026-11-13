<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desactivar en vez de eliminar (05C hardening, pedido explícitamente por el
 * usuario), mismo patrón ya usado en AREA_ACTIVO: estos 4 catálogos son
 * referenciados por FK desde otras tablas (requerimientos, ítems, ofertas,
 * convocatorias/ofertas por su ESTA_ID) — eliminarlos de verdad rompería esas
 * relaciones o exigiría manejar conflictos de FK en cada borrado. Desactivar
 * los saca de los selectores de "nuevo registro" sin tocar el historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotente: en una BD nueva, `MigrationColumns::auditoria()` ya
        // crea estas columnas desde la migración de creación de cada tabla.
        // Esta migración solo las agrega si faltan (p.ej. BD creada antes de
        // que el helper las incluyera).
        if (! Schema::hasColumn('RUBRO', 'RUBR_ACTIVO')) {
            Schema::table('RUBRO', function (Blueprint $table) {
                $table->boolean('RUBR_ACTIVO')->default(true);
            });
        }
        if (! Schema::hasColumn('UNIDAD_MEDIDA', 'UNME_ACTIVO')) {
            Schema::table('UNIDAD_MEDIDA', function (Blueprint $table) {
                $table->boolean('UNME_ACTIVO')->default(true);
            });
        }
        if (! Schema::hasColumn('TIPO_COMPROBANTE', 'TICO_ACTIVO')) {
            Schema::table('TIPO_COMPROBANTE', function (Blueprint $table) {
                $table->boolean('TICO_ACTIVO')->default(true);
            });
        }
        if (! Schema::hasColumn('ESTADO', 'ESTA_ACTIVO')) {
            Schema::table('ESTADO', function (Blueprint $table) {
                $table->boolean('ESTA_ACTIVO')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('RUBRO', 'RUBR_ACTIVO')) {
            Schema::table('RUBRO', function (Blueprint $table) {
                $table->dropColumn('RUBR_ACTIVO');
            });
        }
        if (Schema::hasColumn('UNIDAD_MEDIDA', 'UNME_ACTIVO')) {
            Schema::table('UNIDAD_MEDIDA', function (Blueprint $table) {
                $table->dropColumn('UNME_ACTIVO');
            });
        }
        if (Schema::hasColumn('TIPO_COMPROBANTE', 'TICO_ACTIVO')) {
            Schema::table('TIPO_COMPROBANTE', function (Blueprint $table) {
                $table->dropColumn('TICO_ACTIVO');
            });
        }
        if (Schema::hasColumn('ESTADO', 'ESTA_ACTIVO')) {
            Schema::table('ESTADO', function (Blueprint $table) {
                $table->dropColumn('ESTA_ACTIVO');
            });
        }
    }
};
