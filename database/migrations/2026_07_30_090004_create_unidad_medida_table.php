<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('UNIDAD_MEDIDA', function (Blueprint $table) {
            $table->bigIncrements('UNME_ID');
            $table->string('UNME_CODIGO', 20)->unique();
            $table->string('UNME_NOMBRE', 60);

            MigrationColumns::auditoria($table, 'UNME');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('UNIDAD_MEDIDA');
    }
};
