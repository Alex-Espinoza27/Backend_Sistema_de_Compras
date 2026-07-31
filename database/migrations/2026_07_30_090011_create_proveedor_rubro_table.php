<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PROVEEDOR_RUBRO', function (Blueprint $table) {
            $table->bigIncrements('PRRU_ID');
            $table->unsignedBigInteger('PRRU_PROVEEDOR_ID');
            $table->unsignedBigInteger('PRRU_RUBRO_ID');

            MigrationColumns::auditoria($table, 'PRRU');

            $table->foreign('PRRU_PROVEEDOR_ID')->references('PROV_ID')->on('PROVEEDOR')->cascadeOnDelete();
            $table->foreign('PRRU_RUBRO_ID')->references('RUBR_ID')->on('RUBRO');

            $table->unique(['PRRU_PROVEEDOR_ID', 'PRRU_RUBRO_ID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PROVEEDOR_RUBRO');
    }
};
