<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('UIT', function (Blueprint $table) {
            $table->bigIncrements('UITA_ID');
            $table->integer('UITA_ANIO')->unique();
            $table->decimal('UITA_VALOR', 14, 2);
            $table->boolean('UITA_VIGENTE')->default(false);

            MigrationColumns::auditoria($table, 'UITA');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('UIT');
    }
};
