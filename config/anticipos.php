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
    | Aprobadores de cada etapa. Si una etapa tiene correos configurados se usan SOLO esos; si queda vacía
    | se notifica a los usuarios con el permiso de la etapa (anticipos.aprobar_*) asignados al proyecto.
    |
    */
    'stage_emails' => [
        'director_obra' => $emails('ANTICIPOS_DIRECTOR_EMAILS', 'dirobrasanmiguel@inverconstruccion.com'),
        'gerencia' => $emails('ANTICIPOS_GERENCIA_EMAILS', 'gerentedeproyectos@inverconstruccion.com'),
    ],

    // Contabilidad: genera el anticipo en Multifox. Si se configuran correos se usan SOLO esos; si no, los
    // usuarios con el permiso anticipos.contabilizar asignados al proyecto
    'contabilidad' => $emails('ANTICIPOS_CONTABILIDAD_EMAILS', 'facturacion@inverconstruccion.com'),

    // Copia (CC) de TODOS los correos del flujo
    'monitor' => $emails('ANTICIPOS_MONITOR_EMAILS', 'residentedeobra@inverconstruccion.com'),

    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

];
