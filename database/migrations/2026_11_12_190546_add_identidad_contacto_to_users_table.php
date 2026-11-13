<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tipo_documento_id')->nullable()->after('dni');
            $table->string('celular', 9)->nullable()->after('sexo');
            $table->date('fecha_nacimiento')->nullable()->after('celular');

            $table->foreign('tipo_documento_id')->references('TIDO_ID')->on('TIPO_DOCUMENTO')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tipo_documento_id']);
            $table->dropColumn(['tipo_documento_id', 'celular', 'fecha_nacimiento']);
        });
    }
};
