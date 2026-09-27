<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('certificados_divulgacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->unique()->constrained('reportes')->cascadeOnDelete();
            $table->string('codigo', 40)->unique()->index();
            $table->string('huella', 64)->index();
            $table->text('firma_pgp');
            $table->string('clave_huella', 100)->nullable();
            $table->json('datos');
            $table->foreignId('emitido_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificados_divulgacion');
    }
};
