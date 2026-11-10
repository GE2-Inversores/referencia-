<?php

namespace App\Console\Commands;

use App\Models\InboxMensaje;
use Illuminate\Console\Command;

class InboxPrune extends Command
{
    protected $signature = 'inbox:prune {--dias=0 : Días que un correo sin gestionar se conserva; 0 = solo el día en curso}';

    protected $description = 'Borra de la base de datos los correos de la Bandeja sin gestionar recibidos antes de hoy — los gestionados y negados se conservan';

    public function handle(): int
    {
        $dias = max(0, (int) $this->option('dias'));
        $limite = now()->subDays($dias)->startOfDay();

        $borrados = InboxMensaje::whereNull('gestion')
            ->where('recibido_en', '<', $limite)
            ->delete();

        $this->info("Bandeja depurada: {$borrados} correo(s) sin gestionar anteriores a {$limite->toDateString()} eliminados.");

        return self::SUCCESS;
    }
}
