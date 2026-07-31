<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('REQUERIMIENTO_ITEM', function (Blueprint $table) {
            $table->bigIncrements('REIT_ID');
            $table->unsignedBigInteger('REIT_REQUERIMIENTO_ID');
            $table->smallInteger('REIT_NUMERO');
            $table->text('REIT_DESCRIPCION');
            $table->unsignedBigInteger('REIT_UNIDAD_MEDIDA_ID');
            $table->decimal('REIT_CANTIDAD', 14, 2);

            MigrationColumns::auditoria($table, 'REIT');

            $table->foreign('REIT_REQUERIMIENTO_ID')->references('REQU_ID')->on('REQUERIMIENTO')->cascadeOnDelete();
            $table->foreign('REIT_UNIDAD_MEDIDA_ID')->references('UNME_ID')->on('UNIDAD_MEDIDA');

            $table->unique(['REIT_REQUERIMIENTO_ID', 'REIT_NUMERO']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('REQUERIMIENTO_ITEM');
    }
};
