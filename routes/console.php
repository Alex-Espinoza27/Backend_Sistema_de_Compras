<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cada 2 minutos (pedido explícitamente por el usuario). `withoutOverlapping`
// evita dos corridas a la vez si una tarda; el candado vence a los 10 min
// (no a las 24 h por defecto) por si el proceso muere a mitad, p. ej. al
// reiniciar el servidor. Con `php artisan down` (despliegue) no corre.
// Igual, la postulación y el retiro validan el plazo con la hora del
// servidor sin depender de esta tarea.
Schedule::command('convocatorias:vencer')->everyTwoMinutes()->withoutOverlapping(10);

Schedule::command('respaldo:base-datos')->dailyAt(config('respaldo.hora'));
