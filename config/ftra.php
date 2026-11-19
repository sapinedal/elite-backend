<?php

$emails = fn (string $key, string $default = '') => array_values(array_filter(array_map(
    'trim',
    explode(',', (string) (env($key) ?: $default))
)));

return [

    /*
    |--------------------------------------------------------------------------
    | Destinatarios de las notificaciones del flujo FTRA
    |--------------------------------------------------------------------------
    |
    | Los correos se envían a los usuarios con el permiso correos.ftra.<destinatario> asignado directamente
    | (Usuarios > permisos) y acceso al proyecto. Estas variables son SOLO para buzones externos que no son
    | usuarios de Elite (separados por coma); vacías por defecto.
    |
    */
    'stage_emails' => [
        'supervisor' => $emails('FTRA_SUPERVISOR_EMAILS'),
        'director_obra' => $emails('FTRA_DIRECTOR_EMAILS'),
    ],

    // En copia de todos los correos del flujo (correos.ftra.copia)
    'monitor' => $emails('FTRA_MONITOR_EMAILS'),

    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

];
