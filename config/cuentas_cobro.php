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

    // Aprobadores de cada etapa. Si una etapa tiene correos configurados se usan SOLO esos; si queda vacía
    // se notifica a los usuarios con el permiso de la etapa (cuentas_cobro.aprobar_*) asignados al proyecto.
    'stage_emails' => [
        'director_obra' => $emails('CUENTAS_COBRO_DIRECTOR_EMAILS', 'dirobrasanmiguel@inverconstruccion.com'),
        'gerencia' => $emails('CUENTAS_COBRO_GERENCIA_EMAILS', 'gerentedeproyectos@inverconstruccion.com'),
    ],

    // Copia (CC) de TODOS los correos del flujo
    'monitor' => $emails('CUENTAS_COBRO_MONITOR_EMAILS', 'residentedeobra@inverconstruccion.com'),

    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

];
