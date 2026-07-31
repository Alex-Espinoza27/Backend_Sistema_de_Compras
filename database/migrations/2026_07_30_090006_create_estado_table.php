<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ESTADO', function (Blueprint $table) {
            $table->bigIncrements('ESTA_ID');
            $table->string('ESTA_CONTEXTO', 20);
            $table->string('ESTA_CODIGO', 30);
            $table->string('ESTA_NOMBRE', 80);
            $table->string('ESTA_COLOR', 20);
            $table->smallInteger('ESTA_ORDEN');

            MigrationColumns::auditoria($table, 'ESTA');

            $table->unique(['ESTA_CONTEXTO', 'ESTA_CODIGO']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ESTADO');
    }
};
