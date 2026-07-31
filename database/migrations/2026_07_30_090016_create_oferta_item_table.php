<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('OFERTA_ITEM', function (Blueprint $table) {
            $table->bigIncrements('OFIT_ID');
            $table->unsignedBigInteger('OFIT_OFERTA_ID');
            $table->unsignedBigInteger('OFIT_REQUERIMIENTO_ITEM_ID');
            $table->string('OFIT_MARCA_MODELO', 150)->nullable();
            $table->decimal('OFIT_PRECIO_UNITARIO', 14, 2);
            $table->decimal('OFIT_PRECIO_TOTAL', 14, 2)->default(0);

            MigrationColumns::auditoria($table, 'OFIT');

            $table->foreign('OFIT_OFERTA_ID')->references('OFER_ID')->on('OFERTA')->cascadeOnDelete();
            $table->foreign('OFIT_REQUERIMIENTO_ITEM_ID')->references('REIT_ID')->on('REQUERIMIENTO_ITEM');

            $table->index('OFIT_OFERTA_ID');
            $table->unique(['OFIT_OFERTA_ID', 'OFIT_REQUERIMIENTO_ITEM_ID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('OFERTA_ITEM');
    }
};
