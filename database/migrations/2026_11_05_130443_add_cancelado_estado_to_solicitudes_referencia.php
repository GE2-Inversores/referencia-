<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // En SQLite la columna ya quedó como string en migraciones previas.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE solicitudes_referencia MODIFY COLUMN estado ENUM('pendiente','pendiente_cama','en_espera','completado','negado','cancelado') NOT NULL DEFAULT 'pendiente'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::table('solicitudes_referencia')->where('estado', 'cancelado')->update(['estado' => 'negado']);

        DB::statement("ALTER TABLE solicitudes_referencia MODIFY COLUMN estado ENUM('pendiente','pendiente_cama','en_espera','completado','negado') NOT NULL DEFAULT 'pendiente'");
    }
};
