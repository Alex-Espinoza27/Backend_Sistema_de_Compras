<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('dni', 15)->nullable()->after('email');
            $table->string('nombres', 100)->nullable()->after('dni');
            $table->string('apellido_paterno', 100)->nullable()->after('nombres');
            $table->string('apellido_materno', 100)->nullable()->after('apellido_paterno');
            $table->string('sexo', 1)->nullable()->after('apellido_materno');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['dni', 'nombres', 'apellido_paterno', 'apellido_materno', 'sexo']);
        });
    }
};
