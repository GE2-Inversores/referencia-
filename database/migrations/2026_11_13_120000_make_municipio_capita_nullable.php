<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El municipio deja de ser obligatorio: muchas remisiones por correo no
     * lo traen y no debe impedir la radicación.
     */
    public function up(): void
    {
        Schema::table('solicitudes_referencia', function (Blueprint $table) {
            $table->string('municipio_capita', 120)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_referencia', function (Blueprint $table) {
            $table->string('municipio_capita', 120)->nullable(false)->change();
        });
    }
};
