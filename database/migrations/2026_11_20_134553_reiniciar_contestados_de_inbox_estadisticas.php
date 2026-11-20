<?php

use App\Models\InboxEstadistica;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * «Contestados» ahora solo suma los correos contestados que ya se
     * borraron; los que siguen en la base se cuentan en vivo. Desde la fecha
     * de conteo completo el contador se reinicia para no contarlos dos veces.
     */
    public function up(): void
    {
        DB::table('inbox_estadisticas')
            ->where('fecha', '>=', InboxEstadistica::CONTEO_COMPLETO_DESDE)
            ->update(['contestados' => 0]);
    }

    public function down(): void {}
};
