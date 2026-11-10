<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Marca como radicados los correos que ya tienen una solicitud
     * creada antes de existir la columna gestion.
     */
    public function up(): void
    {
        DB::statement("
            UPDATE inbox_mensajes
            SET gestion = 'radicada',
                gestion_por = (
                    SELECT sr.nombre_quien_responde
                    FROM solicitudes_referencia sr
                    WHERE sr.inbox_mensaje_id = inbox_mensajes.id
                    LIMIT 1
                ),
                gestion_at = (
                    SELECT sr.created_at
                    FROM solicitudes_referencia sr
                    WHERE sr.inbox_mensaje_id = inbox_mensajes.id
                    LIMIT 1
                ),
                gestion_detalle = 'Solicitud registrada desde este correo.'
            WHERE gestion IS NULL
              AND EXISTS (
                    SELECT 1
                    FROM solicitudes_referencia sr
                    WHERE sr.inbox_mensaje_id = inbox_mensajes.id
              )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se revierte: no hay forma de saber qué correos fueron
        // marcados por esta corrección y cuáles por el flujo normal.
    }
};
