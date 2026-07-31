<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('OFERTA', function (Blueprint $table) {
            $table->bigIncrements('OFER_ID');
            $table->unsignedBigInteger('OFER_CONVOCATORIA_ID');
            $table->unsignedBigInteger('OFER_PROVEEDOR_ID');
            $table->unsignedBigInteger('OFER_ESTADO_ID');
            $table->unsignedBigInteger('OFER_TIPO_COMPROBANTE_ID');
            $table->decimal('OFER_TOTAL_GENERAL', 14, 2)->default(0);
            $table->smallInteger('OFER_VALIDEZ_DIAS');
            $table->boolean('OFER_PLAZO_EJECUCION_SEGUN_TDR')->default(false);
            $table->smallInteger('OFER_PLAZO_EJECUCION_DIAS')->nullable();
            $table->boolean('OFER_FORMA_PAGO_SEGUN_TDR')->default(false);
            $table->smallInteger('OFER_FORMA_PAGO_DIAS')->nullable();
            $table->string('OFER_PLAZO_GARANTIA', 120)->nullable();
            $table->string('OFER_CONTACTO_NOMBRE', 120)->nullable();
            $table->string('OFER_CONTACTO_TELEFONO', 20)->nullable();
            $table->string('OFER_CONTACTO_CORREO', 120)->nullable();
            $table->string('OFER_CONSTANCIA_CODIGO', 40)->nullable()->unique();
            $table->timestampTz('OFER_FECHA_ENVIO')->nullable();
            $table->string('OFER_EVAL_TECNICA', 12)->nullable();
            $table->text('OFER_EVAL_TECNICA_MOTIVO')->nullable();
            $table->string('OFER_EVAL_LOGISTICA', 12)->nullable();
            $table->text('OFER_EVAL_LOGISTICA_MOTIVO')->nullable();
            $table->unsignedBigInteger('OFER_EVALUADA_POR')->nullable();
            $table->timestampTz('OFER_FECHA_EVALUACION')->nullable();

            MigrationColumns::auditoria($table, 'OFER', conSoftDelete: true);

            $table->foreign('OFER_CONVOCATORIA_ID')->references('CONV_ID')->on('CONVOCATORIA');
            $table->foreign('OFER_PROVEEDOR_ID')->references('PROV_ID')->on('PROVEEDOR');
            $table->foreign('OFER_ESTADO_ID')->references('ESTA_ID')->on('ESTADO');
            $table->foreign('OFER_TIPO_COMPROBANTE_ID')->references('TICO_ID')->on('TIPO_COMPROBANTE');
            $table->foreign('OFER_EVALUADA_POR')->references('id')->on('users')->nullOnDelete();

            $table->index('OFER_CONVOCATORIA_ID');
            $table->index('OFER_PROVEEDOR_ID');
            $table->index('OFER_ESTADO_ID');
            $table->unique(['OFER_CONVOCATORIA_ID', 'OFER_PROVEEDOR_ID']);
        });

        Schema::table('CONVOCATORIA', function (Blueprint $table) {
            $table->foreign('CONV_OFERTA_ADJUDICADA_ID')->references('OFER_ID')->on('OFERTA')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('CONVOCATORIA', function (Blueprint $table) {
            $table->dropForeign(['CONV_OFERTA_ADJUDICADA_ID']);
        });

        Schema::dropIfExists('OFERTA');
    }
};
