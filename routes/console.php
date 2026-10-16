<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sincronización automática del ERP cuando ERP_FUENTE=directo.
// Requiere `php artisan schedule:work` (o un cron a `schedule:run`).
if (config('erp.fuente') === 'directo') {
    Schedule::command('erp:sincronizar')
        ->everyThirtyMinutes()
        ->withoutOverlapping()
        ->runInBackground();
}

// Sincronización automática del inventario de exportación (SyncBox).
// Requiere `php artisan schedule:work` (o un cron a `schedule:run`).
Schedule::command('inventario:sincronizar')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();
