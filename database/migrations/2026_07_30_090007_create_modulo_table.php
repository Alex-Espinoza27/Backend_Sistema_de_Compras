<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('MODULO', function (Blueprint $table) {
            $table->bigIncrements('MODU_ID');
            $table->string('MODU_CODIGO', 30)->unique();
            $table->string('MODU_NOMBRE', 80);
            $table->string('MODU_ICONO', 40)->nullable();
            $table->smallInteger('MODU_ORDEN');

            MigrationColumns::auditoria($table, 'MODU');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('MODULO');
    }
};
