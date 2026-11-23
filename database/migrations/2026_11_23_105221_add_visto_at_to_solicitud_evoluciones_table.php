<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuándo el personal de referencia abrió la evolución: mientras sea nulo
     * cuenta como «nueva» en el indicador de la lista de solicitudes.
     */
    public function up(): void
    {
        Schema::table('solicitud_evoluciones', function (Blueprint $table) {
            $table->timestamp('visto_at')->nullable()->after('nota');
        });
    }

    public function down(): void
    {
        Schema::table('solicitud_evoluciones', function (Blueprint $table) {
            $table->dropColumn('visto_at');
        });
    }
};
