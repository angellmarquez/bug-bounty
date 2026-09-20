<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->text('descripcion_cifrada')->nullable()->after('descripcion');
            $table->text('poc_cifrado')->nullable()->after('poc');
        });
    }

    public function down(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->dropColumn(['descripcion_cifrada', 'poc_cifrado']);
        });
    }
};
