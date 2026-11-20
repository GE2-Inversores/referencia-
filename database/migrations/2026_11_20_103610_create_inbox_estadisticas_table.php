<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contadores diarios de la Bandeja, por fecha de llegada del correo:
     * solo números, nunca el contenido de los correos.
     */
    public function up(): void
    {
        Schema::create('inbox_estadisticas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->unsignedInteger('recibidos')->default(0);
            $table->unsignedInteger('eliminados')->default(0);
            $table->unsignedInteger('contestados')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_estadisticas');
    }
};
