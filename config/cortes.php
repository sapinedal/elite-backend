<?php

$emails = fn (string $key) => array_values(array_filter(array_map('trim', explode(',', (string) env($key, '')))));

return [

    // Carpeta del bucket donde se guardan los archivos de los cortes
    's3_root' => env('CORTES_S3_ROOT', 'tecnica/cortes'),

    // Tamaño máximo del archivo del corte (MB). Se sube directo a S3 con URL firmada,
    // por eso no depende de upload_max_filesize de PHP
    'max_upload_mb' => (int) env('CORTES_MAX_UPLOAD_MB', 1024),

    // Minutos de validez de la URL firmada de subida
    'upload_url_minutes' => (int) env('CORTES_UPLOAD_URL_MINUTES', 120),

    // Correos adicionales (separados por coma) que reciben los avisos de cada etapa, además de
    // los usuarios con el permiso de la etapa asignados al proyecto. Útil mientras se configuran los roles.
    'stage_emails' => [
        'interventor' => $emails('CORTES_INTERVENTOR_EMAILS'),
        'director_obra' => $emails('CORTES_DIRECTOR_EMAILS'),
        'gerencia' => $emails('CORTES_GERENCIA_EMAILS'),
    ],

    // URL del frontend para el enlace "Ver corte" de los correos
    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

];
