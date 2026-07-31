<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TIPO_DOCUMENTO', function (Blueprint $table) {
            $table->bigIncrements('TIDO_ID');
            $table->string('TIDO_CODIGO', 10)->unique();
            $table->string('TIDO_NOMBRE', 60);
            $table->smallInteger('TIDO_LONGITUD');

            MigrationColumns::auditoria($table, 'TIDO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TIPO_DOCUMENTO');
    }
};
