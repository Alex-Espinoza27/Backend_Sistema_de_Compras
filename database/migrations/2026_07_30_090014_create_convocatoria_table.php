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
        Schema::create('CONVOCATORIA', function (Blueprint $table) {
            $table->bigIncrements('CONV_ID');
            $table->unsignedBigInteger('CONV_REQUERIMIENTO_ID');
            $table->unsignedBigInteger('CONV_ENTIDAD_ID');
            $table->string('CONV_NUMERO', 40)->nullable()->unique();
            $table->string('CONV_NOMBRE_SERVICIO', 250);
            $table->timestampTz('CONV_FECHA_INICIO');
            $table->timestampTz('CONV_FECHA_FIN');
            $table->unsignedBigInteger('CONV_ESTADO_ID');
            $table->text('CONV_MOTIVO_CIERRE')->nullable();
            // FK hacia OFERTA se agrega en una migración posterior (dependencia circular).
            $table->unsignedBigInteger('CONV_OFERTA_ADJUDICADA_ID')->nullable();
            $table->timestampTz('CONV_FECHA_PUBLICACION')->nullable();
            $table->timestampTz('CONV_FECHA_APERTURA')->nullable();
            $table->timestampTz('CONV_FECHA_RESULTADO')->nullable();

            MigrationColumns::auditoria($table, 'CONV', conSoftDelete: true);

            $table->foreign('CONV_REQUERIMIENTO_ID')->references('REQU_ID')->on('REQUERIMIENTO');
            $table->foreign('CONV_ENTIDAD_ID')->references('ENTI_ID')->on('ENTIDAD');
            $table->foreign('CONV_ESTADO_ID')->references('ESTA_ID')->on('ESTADO');

            $table->index('CONV_ESTADO_ID');
            $table->index('CONV_ENTIDAD_ID');
            $table->index('CONV_FECHA_FIN');
        });

        DB::statement('CREATE INDEX conv_nombre_servicio_trgm_idx ON "CONVOCATORIA" USING gin ("CONV_NOMBRE_SERVICIO" gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('CONVOCATORIA');
    }
};
