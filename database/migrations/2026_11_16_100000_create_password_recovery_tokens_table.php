<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tokens del broker `recuperacion` (config/auth.php): mismo formato que
     * `password_reset_tokens`, pero separados para que un enlace de
     * recuperación (10 min) y uno de alta (60 min) no se confundan. */
    public function up(): void
    {
        Schema::create('password_recovery_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_recovery_tokens');
    }
};
