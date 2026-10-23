<?php

// Lista de correos desde el .env (separados por coma). Si la variable no existe o está vacía se usa $default.
$emails = fn (string $key, string $default = '') => array_values(array_filter(array_map(
    'trim',
    explode(',', (string) (env($key) ?: $default))
)));

return [

    // Carpeta del bucket donde se guardan los archivos de los cortes
    's3_root' => env('CORTES_S3_ROOT', 'tecnica/cortes'),

    // Tamaño máximo del archivo del corte (MB). Se sube directo a S3 con URL firmada,
    // por eso no depende de upload_max_filesize de PHP
    'max_upload_mb' => (int) env('CORTES_MAX_UPLOAD_MB', 1024),

    // Minutos de validez de la URL firmada de subida
    'upload_url_minutes' => (int) env('CORTES_UPLOAD_URL_MINUTES', 120),

    /*
    |--------------------------------------------------------------------------
    | Destinatarios de las notificaciones
    |--------------------------------------------------------------------------
    |
    | Revisores de cada etapa. Si una etapa tiene correos configurados se usan SOLO esos; si queda vacía
    | se notifica a los usuarios con el permiso de la etapa (cortes.aprobar_*) asignados al proyecto.
    |
    */
    'stage_emails' => [
        'interventor' => $emails('CORTES_INTERVENTOR_EMAILS', 'druizeqp@gmail.com'),
        'director_obra' => $emails('CORTES_DIRECTOR_EMAILS', 'dirobrasanmiguel@inverconstruccion.com'),
        'gerencia' => $emails('CORTES_GERENCIA_EMAILS', 'gerentedeproyectos@inverconstruccion.com'),
    ],

    // Residentes de obra: reciben, junto con quien radicó el corte, los avisos dirigidos al residente
    'residentes' => $emails('CORTES_RESIDENTES_EMAILS', 'residenteacabados@inverconstruccion.com,residenteobrasanmiguel@inverconstruccion.com'),

    // Facturación: recibe el corte cuando completa las tres aprobaciones
    'facturacion' => $emails('CORTES_FACTURACION_EMAILS', 'facturacion@inverconstruccion.com'),

    // Copia (CC) de TODOS los correos del flujo, para verificar que las notificaciones están llegando
    'monitor' => $emails('CORTES_MONITOR_EMAILS', 'residentedeobra@inverconstruccion.com'),

    // URL del frontend para el enlace "Ver corte" de los correos
    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

];
