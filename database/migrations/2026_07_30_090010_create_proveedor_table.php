<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PROVEEDOR', function (Blueprint $table) {
            $table->bigIncrements('PROV_ID');
            $table->unsignedBigInteger('PROV_USER_ID')->unique();
            $table->unsignedBigInteger('PROV_TIPO_DOCUMENTO_ID');
            $table->string('PROV_NUMERO_DOCUMENTO', 15)->unique();
            $table->string('PROV_TIPO_PERSONA', 10);
            $table->string('PROV_RAZON_SOCIAL', 200);
            $table->string('PROV_CORREO', 120)->nullable();
            $table->string('PROV_TELEFONO', 20)->nullable();
            $table->string('PROV_PERSONA_CONTACTO', 120)->nullable();
            $table->unsignedBigInteger('PROV_ESTADO_ID');
            $table->jsonb('PROV_RESPUESTA_SUNAT')->nullable();
            $table->string('PROV_ORIGEN_DATO', 10);

            MigrationColumns::auditoria($table, 'PROV', conSoftDelete: true);

            $table->foreign('PROV_USER_ID')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('PROV_TIPO_DOCUMENTO_ID')->references('TIDO_ID')->on('TIPO_DOCUMENTO');
            $table->foreign('PROV_ESTADO_ID')->references('ESTA_ID')->on('ESTADO');
        });

        DB::statement('CREATE INDEX prov_razon_social_trgm_idx ON "PROVEEDOR" USING gin ("PROV_RAZON_SOCIAL" gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('PROVEEDOR');
    }
};
