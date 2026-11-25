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
        Schema::table('solicitudes_referencia', function (Blueprint $table) {
            $table->unsignedTinyInteger('tiempo_llegada_horas')->nullable()->after('hora_llegada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_referencia', function (Blueprint $table) {
            $table->dropColumn('tiempo_llegada_horas');
        });
    }
};
