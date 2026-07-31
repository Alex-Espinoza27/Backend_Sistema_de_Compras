<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('CONFIGURACION', function (Blueprint $table) {
            $table->bigIncrements('CONF_ID');
            $table->string('CONF_CLAVE', 80)->unique();
            $table->text('CONF_VALOR')->nullable();
            $table->string('CONF_TIPO', 20);
            $table->string('CONF_DESCRIPCION', 255)->nullable();

            MigrationColumns::auditoria($table, 'CONF');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('CONFIGURACION');
    }
};
