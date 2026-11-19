<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cada 2 minutos (pedido explícitamente por el usuario). `withoutOverlapping`
// evita dos corridas a la vez si una tarda; igual, la postulación y el retiro
// validan el plazo con la hora del servidor sin depender de esta tarea.
Schedule::command('convocatorias:vencer')->everyTwoMinutes()->withoutOverlapping();

Schedule::command('respaldo:base-datos')->dailyAt(config('respaldo.hora'));
