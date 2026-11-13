<?php

namespace App\Console\Commands;

use App\Services\GmailService;
use Illuminate\Console\Command;

class InboxVigilar extends Command
{
    protected $signature = 'inbox:vigilar {--minutos=15 : Minutos sin sincronizar antes de avisar}';

    protected $description = 'Avisa a quienes ven la Bandeja si lleva demasiado tiempo sin sincronizar con Gmail (y cuando se recupera)';

    public function handle(GmailService $gmail): int
    {
        $gmail->vigilar(max(1, (int) $this->option('minutos')));

        return self::SUCCESS;
    }
}
