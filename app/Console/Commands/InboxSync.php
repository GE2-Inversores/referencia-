<?php

namespace App\Console\Commands;

use App\Services\GmailService;
use Illuminate\Console\Command;
use Throwable;

class InboxSync extends Command
{
    protected $signature = 'inbox:sync {--completo : Recorre todo el historial del buzón} {--auditar : Revisa todo lo llegado hoy y recupera lo que falte} {--limite=500 : Máximo de mensajes nuevos por ejecución}';

    protected $description = 'Sincroniza la bandeja del aplicativo con los correos del buzón de Gmail';

    public function handle(GmailService $gmail): int
    {
        if (! $gmail->configurado()) {
            $this->warn('Gmail no configurado — define el buzón y su contraseña/credenciales en Configuración → Correo.');

            return self::SUCCESS;
        }

        try {
            $r = $gmail->sincronizar(
                completo: (bool) $this->option('completo'),
                auditar: (bool) $this->option('auditar'),
                limite: (int) $this->option('limite'),
            );

            $this->info("Bandeja sincronizada: {$r['agregados']} nuevos de {$r['examinados']} examinados.");
        } catch (Throwable $e) {
            $gmail->registrarError($e->getMessage());
            $this->error('Falló la sincronización: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
