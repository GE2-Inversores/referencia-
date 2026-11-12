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
        Schema::create('solicitud_evolucion_respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_evolucion_id')->constrained('solicitud_evoluciones')->cascadeOnDelete();
            $table->foreignId('solicitud_referencia_id')->constrained('solicitudes_referencia')->cascadeOnDelete();
            $table->text('mensaje');
            $table->string('respondido_por', 200)->nullable();
            $table->boolean('correo_enviado')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_evolucion_respuestas');
    }
};
