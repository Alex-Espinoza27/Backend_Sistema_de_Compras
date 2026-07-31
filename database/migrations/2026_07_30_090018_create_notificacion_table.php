<?php

use App\Support\MigrationColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('NOTIFICACION', function (Blueprint $table) {
            $table->bigIncrements('NOTI_ID');
            $table->unsignedBigInteger('NOTI_USER_ID');
            $table->string('NOTI_TIPO', 30);
            $table->string('NOTI_TITULO', 150);
            $table->text('NOTI_MENSAJE');
            $table->boolean('NOTI_LEIDA')->default(false);
            $table->string('NOTI_REFERENCIA_TIPO', 20)->nullable();
            $table->unsignedBigInteger('NOTI_REFERENCIA_ID')->nullable();

            MigrationColumns::auditoria($table, 'NOTI');

            $table->foreign('NOTI_USER_ID')->references('id')->on('users')->cascadeOnDelete();

            $table->index(['NOTI_USER_ID', 'NOTI_LEIDA']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('NOTIFICACION');
    }
};
