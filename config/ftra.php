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
    | Revisores de cada etapa. Se avisa a los usuarios que tienen el permiso de la etapa asignado
    | DIRECTAMENTE (no por rol) y acceso al proyecto, más los correos configurados aquí.
    |   - supervisor:    ftra.revisar  (FTRA_SUPERVISOR_EMAILS)
    |   - director_obra: ftra.aprobar  (FTRA_DIRECTOR_EMAILS)
    |
    */
    'stage_emails' => [
        'supervisor' => $emails('FTRA_SUPERVISOR_EMAILS', 'druiz.eqp@gmail.com,gaguilar.eqp@gmail.com'),
        'director_obra' => $emails('FTRA_DIRECTOR_EMAILS', 'dirobrasanmiguel@inverconstruccion.com'),
    ],

    // En copia de todos los correos del flujo
    'monitor' => $emails('FTRA_MONITOR_EMAILS'),

    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

];
