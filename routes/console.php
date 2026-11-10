<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inbox:sync')->everyMinute()->withoutOverlapping();
// Solo persisten en BD los correos gestionados/negados: cada medianoche
// se borran los pendientes de días anteriores (siguen en Gmail si hacen falta).
Schedule::command('inbox:prune')->daily();
