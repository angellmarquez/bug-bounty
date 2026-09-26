<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tema visual elegido por el usuario (terminal, corporativo, neon o auto).
     * Null = el tema por defecto. Se guarda en la cuenta para verlo igual en cualquier dispositivo.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tema', 20)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tema');
        });
    }
};
