<?php

// Lista desde el .env (separada por $separator). Si la variable no existe o está vacía se usa $default.
$emails = fn (string $key, string $default = '', string $separator = ',') => array_values(array_filter(array_map(
    'trim',
    explode($separator, (string) (env($key) ?: $default))
)));

return [

    // Cada anticipo se guarda en la carpeta de su contrato: <carpeta del contrato>/<folder>/<código>
    'folder' => env('ANTICIPOS_FOLDER', '08 ANTICIPOS'),

    // Tamaño máximo de la cuenta de cobro y del soporte de Multifox (MB); se suben directo a S3 con URL firmada
    'max_upload_mb' => (int) env('ANTICIPOS_MAX_UPLOAD_MB', 100),

    /*
    |--------------------------------------------------------------------------
    | Destinatarios de las notificaciones
    |--------------------------------------------------------------------------
    |
    | Los correos se envían a los usuarios con el permiso correos.anticipos.<destinatario> asignado directamente
    | (Usuarios > permisos) y acceso al proyecto. Estas variables son SOLO para buzones externos que no son
    | usuarios de Elite (separados por coma); vacías por defecto.
    |
    */
    'stage_emails' => [
        'director_obra' => $emails('ANTICIPOS_DIRECTOR_EMAILS'),
        'gerencia' => $emails('ANTICIPOS_GERENCIA_EMAILS'),
    ],

    // Contabilidad (correos.anticipos.contabilidad): genera el anticipo en Multifox
    'contabilidad' => $emails('ANTICIPOS_CONTABILIDAD_EMAILS'),

    // Copia (CC) de TODOS los correos del flujo (correos.anticipos.copia)
    'monitor' => $emails('ANTICIPOS_MONITOR_EMAILS'),

    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

];
