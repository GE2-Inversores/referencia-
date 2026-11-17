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
            // Histórico, cola activa y conteos del dashboard: filtran por estado y ordenan/acotan por fecha.
            $table->index(['estado', 'created_at'], 'sol_ref_estado_created_idx');
            // Rango del dashboard sin filtro de estado.
            $table->index('created_at', 'sol_ref_created_idx');
            // Rango de fechas de Informes.
            $table->index('fecha', 'sol_ref_fecha_idx');
            // Aviso de posible duplicado y búsquedas exactas por documento.
            $table->index('numero_documento', 'sol_ref_documento_idx');
            // Filtros y desplegables (DISTINCT) de histórico y dashboard.
            $table->index('eps', 'sol_ref_eps_idx');
            $table->index('especialidad_requerida', 'sol_ref_especialidad_idx');
        });

        Schema::table('inbox_mensajes', function (Blueprint $table) {
            // Lista de la Bandeja: carpeta (o Entrada = sin carpeta) ordenada por llegada.
            $table->index(['carpeta', 'recibido_en'], 'inbox_carpeta_recibido_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_referencia', function (Blueprint $table) {
            $table->dropIndex('sol_ref_estado_created_idx');
            $table->dropIndex('sol_ref_created_idx');
            $table->dropIndex('sol_ref_fecha_idx');
            $table->dropIndex('sol_ref_documento_idx');
            $table->dropIndex('sol_ref_eps_idx');
            $table->dropIndex('sol_ref_especialidad_idx');
        });

        Schema::table('inbox_mensajes', function (Blueprint $table) {
            $table->dropIndex('inbox_carpeta_recibido_idx');
        });
    }
};
