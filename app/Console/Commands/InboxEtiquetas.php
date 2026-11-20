<?php

namespace App\Console\Commands;

use App\Models\InboxMensaje;
use App\Services\GmailService;
use Illuminate\Console\Command;

class InboxEtiquetas extends Command
{
    protected $signature = 'inbox:etiquetas {--limite=500 : Máximo de correos por corrida}';

    protected $description = 'Trae del buzón las etiquetas de Gmail (X-GM-LABELS) para los correos guardados antes del soporte de etiquetas';

    public function handle(GmailService $gmail): int
    {
        $limite = max(1, (int) $this->option('limite'));

        // Candidatos baratos: sin etiquetas o con alguna que empiece por
        // contrabarra (banderas y átomos de sistema); necesitaEtiquetas()
        // hace la distinción fina ya en PHP.
        $mensajes = InboxMensaje::query()
            ->where(function ($w): void {
                $w->whereNull('etiquetas')
                    ->orWhere('etiquetas', 'like', '%\\\\%');
            })
            ->orderByDesc('id')
            ->limit($limite * 4)
            ->get(['id', 'gmail_id', 'etiquetas'])
            ->filter(fn (InboxMensaje $m): bool => $m->necesitaEtiquetas())
            ->take($limite)
            ->values();

        $actualizados = 0;

        foreach ($mensajes as $mensaje) {
            $gmail->refrescarEtiquetas($mensaje);
            $actualizados += $mensaje->fresh()->necesitaEtiquetas() ? 0 : 1;
        }

        $this->info("Etiquetas repobladas: {$actualizados} de {$mensajes->count()} correo(s) actualizados.");

        return self::SUCCESS;
    }
}
