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
        Schema::create('inbox_respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbox_mensaje_id')->constrained('inbox_mensajes')->cascadeOnDelete();
            $table->string('remitente', 200);
            $table->string('para', 150);
            $table->json('cc')->nullable();
            $table->text('cuerpo_texto');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbox_respuestas');
    }
};
