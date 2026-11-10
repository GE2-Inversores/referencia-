<?php

namespace App\Console\Commands;

use App\Services\GmailService;
use Illuminate\Console\Command;
use Throwable;

class InboxListen extends Command
{
    protected $signature = 'inbox:listen {--espera=10 : Segundos de espera antes de reconectar tras un fallo}';

    protected $description = 'Escucha el buzón en vivo por IMAP IDLE y registra cada correo al instante';

    public function handle(GmailService $gmail): int
    {
        if (! $gmail->configurado()) {
            $this->warn('Gmail no configurado — define el buzón y sus credenciales en Configuración → Correo.');

            return self::SUCCESS;
        }

        if (! $gmail->esImap()) {
            $this->warn('La escucha en vivo solo aplica al modo IMAP (contraseña de aplicación).');

            return self::SUCCESS;
        }

        $this->info("Escuchando {$gmail->buzon()} en vivo (IDLE). Cada correo nuevo se registra al instante.");

        // IDLE caduca cada pocos minutos y la red puede caer: si escuchar()
        // retorna o lanza un error, se reconecta y sigue escuchando.
        while (true) {
            try {
                $gmail->escuchar();
            } catch (Throwable $e) {
                $gmail->registrarError($e->getMessage());
                $this->warn('Conexión IDLE interrumpida ('.$e->getMessage().') — reconectando…');
                sleep(max(1, (int) $this->option('espera')));
            }
        }
    }
}
