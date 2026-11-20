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
        // Por directriz todo correo recibe respuesta: se registra quién
        // contestó, cuándo y qué se envió, sin tocar la gestión del correo
        // (radicar/negar sigue siendo un paso aparte).
        Schema::table('inbox_mensajes', function (Blueprint $table) {
            $table->dateTime('contestado_at')->nullable()->index()->after('gestion_at');
            $table->string('contestado_por', 150)->nullable()->after('contestado_at');
            $table->text('contestado_detalle')->nullable()->after('contestado_por');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inbox_mensajes', function (Blueprint $table) {
            $table->dropIndex(['contestado_at']);
            $table->dropColumn(['contestado_at', 'contestado_por', 'contestado_detalle']);
        });
    }
};
