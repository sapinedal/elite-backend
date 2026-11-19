<?php

// Lista desde el .env (separada por $separator). Si la variable no existe o está vacía se usa $default.
$emails = fn (string $key, string $default = '', string $separator = ',') => array_values(array_filter(array_map(
    'trim',
    explode($separator, (string) (env($key) ?: $default))
)));

return [

    // Carpeta del bucket: <s3_root>/<proyecto>/<tipo>/<código>/{cuenta de cobro, adjuntos, soporte Multifox}
    's3_root' => env('CUENTAS_COBRO_S3_ROOT', 'tecnica/Cuentas de Cobro'),

    // Tamaño máximo de cada archivo (MB); se sube directo a S3 con URL firmada
    'max_upload_mb' => (int) env('CUENTAS_COBRO_MAX_UPLOAD_MB', 100),

    // Los correos se envían a los usuarios con el permiso correos.cuentas_cobro.<destinatario> asignado directamente
    // y acceso al proyecto. Estas variables son SOLO para buzones externos que no son usuarios de Elite.
    'stage_emails' => [
        'director_obra' => $emails('CUENTAS_COBRO_DIRECTOR_EMAILS'),
        'gerencia' => $emails('CUENTAS_COBRO_GERENCIA_EMAILS'),
    ],

    // Copia (CC) de TODOS los correos del flujo (correos.cuentas_cobro.copia)
    'monitor' => $emails('CUENTAS_COBRO_MONITOR_EMAILS'),

    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

];
