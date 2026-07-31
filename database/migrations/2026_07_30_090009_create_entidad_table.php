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
        Schema::create('ENTIDAD', function (Blueprint $table) {
            $table->bigIncrements('ENTI_ID');
            $table->unsignedBigInteger('ENTI_TIPO_DOCUMENTO_ID');
            $table->string('ENTI_NUMERO_DOCUMENTO', 15)->unique();
            $table->string('ENTI_RAZON_SOCIAL', 200);
            $table->string('ENTI_SIGLA', 30);
            $table->string('ENTI_CORREO', 120)->nullable();
            $table->string('ENTI_TELEFONO', 20)->nullable();
            $table->string('ENTI_DIRECCION', 200)->nullable();
            $table->string('ENTI_PERSONA_CONTACTO', 120)->nullable();
            $table->unsignedBigInteger('ENTI_ESTADO_ID');
            $table->text('ENTI_MOTIVO_OBSERVACION')->nullable();
            $table->timestampTz('ENTI_FECHA_SOLICITUD');
            $table->timestampTz('ENTI_FECHA_RESOLUCION')->nullable();
            $table->unsignedBigInteger('ENTI_RESUELTA_POR')->nullable();
            $table->integer('ENTI_CORRELATIVO_CONVOCATORIA')->default(0);
            $table->integer('ENTI_CORRELATIVO_ANIO')->nullable();

            MigrationColumns::auditoria($table, 'ENTI', conSoftDelete: true);

            $table->foreign('ENTI_TIPO_DOCUMENTO_ID')->references('TIDO_ID')->on('TIPO_DOCUMENTO');
            $table->foreign('ENTI_ESTADO_ID')->references('ESTA_ID')->on('ESTADO');
            $table->foreign('ENTI_RESUELTA_POR')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement('CREATE INDEX enti_razon_social_trgm_idx ON "ENTIDAD" USING gin ("ENTI_RAZON_SOCIAL" gin_trgm_ops)');

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('entidad_id')->references('ENTI_ID')->on('ENTIDAD')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['entidad_id']);
        });

        Schema::dropIfExists('ENTIDAD');
    }
};
