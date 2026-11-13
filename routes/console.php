<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inbox:sync')->everyMinute()->withoutOverlapping(10);
// Solo persisten en BD los correos gestionados/negados: cada medianoche
// se borran los pendientes de días anteriores (siguen en Gmail si hacen falta).
Schedule::command('inbox:prune')->daily();

// Red de seguridad: cada hora compara todo lo llegado hoy a Gmail contra el
// aplicativo y recupera cualquier correo que se hubiera quedado por fuera.
Schedule::command('inbox:sync --auditar')->hourly()->withoutOverlapping(10);

// Vigilante: avisa si la Bandeja lleva más de 15 minutos sin sincronizar.
Schedule::command('inbox:vigilar')->everyFiveMinutes();
