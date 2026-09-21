<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Descripción, dirigida a los investigadores, de los bugs que el programa busca.
     */
    public function up(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->text('bugs_buscados')->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->dropColumn('bugs_buscados');
        });
    }
};
