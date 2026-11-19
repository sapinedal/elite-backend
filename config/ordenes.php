<?php

// Lista desde el .env (separada por $separator). Si la variable no existe o está vacía se usa $default.
$emails = fn (string $key, string $default = '', string $separator = ',') => array_values(array_filter(array_map(
    'trim',
    explode($separator, (string) (env($key) ?: $default))
)));

return [

    // Carpeta del bucket: <s3_root>/<proyecto>/<torre>/<código> - <descripción>/{01 Orden ERP, 02 Factura proveedor, 03 Entrada almacen}
    's3_root' => env('ORDENES_S3_ROOT', 'tecnica/Ordenes de Compra'),

    // Tamaño máximo de cada documento (MB); se sube directo a S3 con URL firmada
    'max_upload_mb' => (int) env('ORDENES_MAX_UPLOAD_MB', 1024),

    /*
    |--------------------------------------------------------------------------
    | Destinatarios de las notificaciones
    |--------------------------------------------------------------------------
    |
    | Los correos se envían a los usuarios con el permiso correos.ordenes.<destinatario> asignado directamente
    | (Usuarios > permisos) y acceso al proyecto. Estas variables son SOLO para buzones externos que no son
    | usuarios de Elite (separados por coma); vacías por defecto.
    |
    */
    'stage_emails' => [
        'director_obra' => $emails('ORDENES_DIRECTOR_EMAILS'),
        'gerencia' => $emails('ORDENES_GERENCIA_EMAILS'),
    ],

    // Almacén (correos.ordenes.almacen): carga la orden de compra del ERP y la entrada
    'almacen_emails' => $emails('ORDENES_ALMACEN_EMAILS'),

    // Facturación (correos.ordenes.facturacion): carga la factura del proveedor
    'facturacion' => $emails('ORDENES_FACTURACION_EMAILS'),

    // Copia (CC) de TODOS los correos del flujo (correos.ordenes.copia)
    'monitor' => $emails('ORDENES_MONITOR_EMAILS'),

    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

];
