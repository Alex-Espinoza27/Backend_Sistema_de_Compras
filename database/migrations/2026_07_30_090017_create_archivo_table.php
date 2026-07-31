<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ARCHIVO', function (Blueprint $table) {
            $table->bigIncrements('ARCH_ID');
            $table->string('ARCH_CONTEXTO', 30);
            $table->unsignedBigInteger('ARCH_REFERENCIA_ID');
            $table->string('ARCH_NOMBRE_ORIGINAL', 255);
            $table->string('ARCH_RUTA', 500);
            $table->string('ARCH_MIME', 120);
            $table->unsignedBigInteger('ARCH_TAMANO_BYTES');
            $table->char('ARCH_HASH_SHA256', 64)->nullable();

            MigrationColumns::auditoria($table, 'ARCH');

            $table->index(['ARCH_CONTEXTO', 'ARCH_REFERENCIA_ID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ARCHIVO');
    }
};
