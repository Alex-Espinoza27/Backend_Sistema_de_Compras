<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('RUBRO', function (Blueprint $table) {
            $table->bigIncrements('RUBR_ID');
            $table->string('RUBR_TIPO', 10);
            $table->string('RUBR_CODIGO', 20);
            $table->string('RUBR_NOMBRE', 150);

            MigrationColumns::auditoria($table, 'RUBR');

            $table->unique(['RUBR_TIPO', 'RUBR_CODIGO']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('RUBRO');
    }
};
