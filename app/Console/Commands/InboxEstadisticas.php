<?php

namespace App\Console\Commands;

use App\Models\InboxEstadistica;
use App\Models\InboxMensaje;
use App\Services\GmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class InboxEstadisticas extends Command
{
    protected $signature = 'inbox:estadisticas {--desde=2026-11-17 : Primer día a rellenar} {--hasta= : Último día (por defecto, hoy)}';

    protected $description = 'Rellena los contadores diarios de la Bandeja contando los correos que llegaron a Gmail en cada día (solo guarda números)';

    public function handle(GmailService $gmail): int
    {
        if (! $gmail->configurado()) {
            $this->error('El buzón de Gmail no está configurado.');

            return self::FAILURE;
        }

        $desde = Carbon::parse((string) $this->option('desde'))->startOfDay();
        $hasta = $this->option('hasta') ? Carbon::parse((string) $this->option('hasta'))->endOfDay() : now();

        try {
            $conteos = $gmail->contarRecibidosPorDia($desde, $hasta);
        } catch (Throwable $e) {
            $this->error('No se pudo contar en Gmail: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach ($conteos as $fecha => $recibidos) {
            $dia = Carbon::parse($fecha);
            InboxEstadistica::fijar('recibidos', $dia, $recibidos);

            // Los contestados que aún están en la base (la limpieza borra el resto).
            $contestados = InboxMensaje::whereNotNull('contestado_at')
                ->whereDate('recibido_en', $fecha)
                ->count();
            $actual = (int) InboxEstadistica::where('fecha', $fecha)->value('contestados');
            InboxEstadistica::fijar('contestados', $dia, max($actual, $contestados));

            $this->line("{$fecha}: {$recibidos} recibidos".($contestados > 0 ? ", {$contestados} contestados" : ''));
        }

        $this->info('Contadores actualizados. Los eliminados de días anteriores no se pueden reconstruir y quedan en 0.');

        return self::SUCCESS;
    }
}
