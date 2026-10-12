<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Respaldo diario (requiere el cron de Laravel: * * * * * php artisan schedule:run).
Schedule::command('solarfix:backup')->dailyAt('02:00')->withoutOverlapping();

// Señal de vida del programador: la revisión del sistema avisa si el cron no está corriendo.
Schedule::call(fn () => touch(storage_path('app/schedule-heartbeat')))->everyFiveMinutes()->name('schedule-heartbeat');
