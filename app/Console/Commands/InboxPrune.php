<?php

namespace App\Console\Commands;

use App\Models\InboxEstadistica;
use App\Models\InboxMensaje;
use App\Services\GmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class InboxPrune extends Command
{
    protected $signature = 'inbox:prune {--dias=0 : Días que un correo sin gestionar se conserva; 0 = solo el día en curso}';

    protected $description = 'Borra de la base de datos los correos de la Bandeja sin gestionar que entraron antes de hoy — los gestionados y negados se conservan';

    public function handle(): int
    {
        $dias = max(0, (int) $this->option('dias'));
        $limite = now()->subDays($dias)->startOfDay();

        $pendientes = InboxMensaje::whereNull('gestion')->where('created_at', '<', $limite);

        // Antes de borrar se deja el número: por día de llegada, para que el
        // informe muestre cuántos se descartaron sin guardar ningún correo.
        $pendientes->clone()
            ->get(['id', 'recibido_en', 'created_at', 'contestado_at'])
            ->groupBy(fn (InboxMensaje $m): string => ($m->recibido_en ?? $m->created_at)->toDateString())
            ->each(function ($grupo, string $dia): void {
                $contestados = $grupo->whereNotNull('contestado_at')->count();

                InboxEstadistica::sumar('contestados', Carbon::parse($dia), $contestados);
                InboxEstadistica::sumar('eliminados', Carbon::parse($dia), $grupo->count() - $contestados);
            });

        $borrados = $pendientes->delete();

        app(GmailService::class)->marcarCorteDelDia();

        $this->info("Bandeja depurada: {$borrados} correo(s) sin gestionar anteriores a {$limite->toDateString()} eliminados.");

        return self::SUCCESS;
    }
}
