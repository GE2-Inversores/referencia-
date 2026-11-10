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
        Schema::create('inbox_mensajes', function (Blueprint $table) {
            $table->id();
            $table->string('gmail_id', 40)->unique();
            $table->string('thread_id', 40)->nullable()->index();
            $table->string('remitente', 500)->nullable();
            $table->string('remitente_email')->nullable();
            $table->string('para', 500)->nullable();
            $table->string('asunto', 500)->nullable();
            $table->string('snippet', 500)->nullable();
            $table->longText('cuerpo_texto')->nullable();
            $table->longText('cuerpo_html')->nullable();
            $table->boolean('tiene_adjuntos')->default(false);
            $table->json('adjuntos')->nullable();
            $table->json('etiquetas')->nullable();
            $table->dateTime('recibido_en')->index();
            $table->boolean('leido')->default(false)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbox_mensajes');
    }
};
