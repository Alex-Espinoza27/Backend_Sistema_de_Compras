<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AUDITORIA es de solo inserción (01 §4.5): no lleva las columnas de auditoría
 * genéricas de §1.1 (no tiene sentido _ACTUALIZADO_EN/_POR ni _ACTIVO en una
 * bitácora inmutable); ya trae su propio sello de tiempo (AUDI_FECHA) y autor
 * (AUDI_USER_ID).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('AUDITORIA', function (Blueprint $table) {
            $table->bigIncrements('AUDI_ID');
            $table->unsignedBigInteger('AUDI_USER_ID')->nullable();
            $table->string('AUDI_MODULO', 30);
            $table->string('AUDI_ACCION', 30);
            $table->string('AUDI_TABLA', 40)->nullable();
            $table->unsignedBigInteger('AUDI_REGISTRO_ID')->nullable();
            $table->jsonb('AUDI_VALOR_ANTERIOR')->nullable();
            $table->jsonb('AUDI_VALOR_NUEVO')->nullable();
            $table->string('AUDI_IP', 45)->nullable();
            $table->string('AUDI_USER_AGENT', 255)->nullable();
            $table->string('AUDI_ENDPOINT', 150)->nullable();
            $table->timestampTz('AUDI_FECHA');

            $table->foreign('AUDI_USER_ID')->references('id')->on('users')->nullOnDelete();

            $table->index('AUDI_FECHA');
            $table->index('AUDI_MODULO');
            $table->index('AUDI_USER_ID');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('AUDITORIA');
    }
};
