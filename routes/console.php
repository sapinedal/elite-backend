<?php

use Illuminate\Foundation\Inspiring;
use App\Http\Modules\Documental\Service\DocumentalService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reconstruye el índice de búsqueda del repositorio documental (incluye lo subido por fuera de la app, ej. rclone)
Artisan::command('documental:index', function (DocumentalService $service) {
    $start = microtime(true);
    $index = $service->searchIndex(true);
    $this->info(sprintf('Índice documental: %d archivos en %.1fs', count($index['files']), microtime(true) - $start));
})->purpose('Reconstruye el índice de búsqueda del módulo documental');

Schedule::command('documental:index')->everyTenMinutes()->withoutOverlapping();
