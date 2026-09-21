<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El acceso a un programa ya no se pide en puntos sueltos sino por nivel (bajo, medio, alto),
 * y cada nivel corresponde a un rango de reputación (bronce, plata, oro…) definido en config/reputacion.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->string('nivel_acceso', 10)->default('bajo')->after('es_publico');
            $table->index('nivel_acceso');
        });

        // Los programas existentes conservan, en lo posible, la exigencia que tenían:
        // 300+ puntos → alto, 100+ → medio, el resto → bajo.
        DB::table('programas')->where('reputacion_minima', '>=', 300)->update(['nivel_acceso' => 'alto']);
        DB::table('programas')->where('reputacion_minima', '>=', 100)->where('reputacion_minima', '<', 300)->update(['nivel_acceso' => 'medio']);

        Schema::table('programas', function (Blueprint $table) {
            $table->dropIndex(['reputacion_minima']);
            $table->dropColumn('reputacion_minima');
        });
    }

    public function down(): void
    {
        Schema::table('programas', function (Blueprint $table) {
            $table->unsignedInteger('reputacion_minima')->default(0)->after('es_publico');
            $table->index('reputacion_minima');
        });

        DB::table('programas')->where('nivel_acceso', 'alto')->update(['reputacion_minima' => 300]);
        DB::table('programas')->where('nivel_acceso', 'medio')->update(['reputacion_minima' => 100]);

        Schema::table('programas', function (Blueprint $table) {
            $table->dropIndex(['nivel_acceso']);
            $table->dropColumn('nivel_acceso');
        });
    }
};
