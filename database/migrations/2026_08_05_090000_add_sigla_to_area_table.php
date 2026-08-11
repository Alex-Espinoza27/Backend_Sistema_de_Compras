<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sigla del área (05C hardening, pedido explícitamente por el usuario) —
 * opcional: las áreas ya existentes (creadas por backfill) no tienen una.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('AREA', function (Blueprint $table) {
            $table->string('AREA_SIGLA', 20)->nullable()->after('AREA_NOMBRE');
        });
    }

    public function down(): void
    {
        Schema::table('AREA', function (Blueprint $table) {
            $table->dropColumn('AREA_SIGLA');
        });
    }
};
