<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('REQUERIMIENTO', function (Blueprint $table) {
            $table->bigIncrements('REQU_ID');
            $table->unsignedBigInteger('REQU_ENTIDAD_ID');
            $table->unsignedBigInteger('REQU_RUBRO_ID')->nullable();
            $table->string('REQU_NUMERO', 40);
            $table->string('REQU_OBJETO', 250);
            $table->text('REQU_DESCRIPCION')->nullable();
            $table->string('REQU_TIPO', 10);
            $table->string('REQU_AREA_USUARIA', 150)->nullable();

            MigrationColumns::auditoria($table, 'REQU', conSoftDelete: true);

            $table->foreign('REQU_ENTIDAD_ID')->references('ENTI_ID')->on('ENTIDAD');
            $table->foreign('REQU_RUBRO_ID')->references('RUBR_ID')->on('RUBRO')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('REQUERIMIENTO');
    }
};
