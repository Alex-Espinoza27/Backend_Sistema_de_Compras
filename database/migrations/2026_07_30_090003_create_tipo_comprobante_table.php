<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('TIPO_COMPROBANTE', function (Blueprint $table) {
            $table->bigIncrements('TICO_ID');
            $table->string('TICO_CODIGO', 20)->unique();
            $table->string('TICO_NOMBRE', 60);
            $table->boolean('TICO_INCLUYE_IGV')->default(false);

            MigrationColumns::auditoria($table, 'TICO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('TIPO_COMPROBANTE');
    }
};
