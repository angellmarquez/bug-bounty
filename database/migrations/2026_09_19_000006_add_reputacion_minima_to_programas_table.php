<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->unsignedInteger('reputacion_minima')->default(0)->after('es_publico');
            $table->index('reputacion_minima');
        });
    }

    public function down(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->dropIndex(['reputacion_minima']);
            $table->dropColumn('reputacion_minima');
        });
    }
};
