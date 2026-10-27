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
    | Aprobadores de cada etapa. Si una etapa tiene correos configurados se usan SOLO esos; si queda vacía
    | se notifica a los usuarios con el permiso de la etapa (ordenes.aprobar_*) asignados al proyecto.
    |
    */
    'stage_emails' => [
        'director_obra' => $emails('ORDENES_DIRECTOR_EMAILS', 'dirobrasanmiguel@inverconstruccion.com'),
        'gerencia' => $emails('ORDENES_GERENCIA_EMAILS', 'gerentedeproyectos@inverconstruccion.com'),
    ],

    // Almacén: carga la orden de compra del ERP y la entrada. Se notifica (y se habilita) a los usuarios con
    // estos CARGOS (Configuración → Áreas y Cargos), separados con |. Si nadie tiene el cargo se usan los
    // correos de respaldo de ORDENES_ALMACEN_EMAILS.
    'almacen_positions' => $emails('ORDENES_ALMACEN_CARGOS', 'Almacenista de Obra', '|'),
    'almacen_emails' => $emails('ORDENES_ALMACEN_EMAILS'),

    // Facturación: carga la factura del proveedor
    'facturacion' => $emails('ORDENES_FACTURACION_EMAILS', 'facturacion@inverconstruccion.com'),

    // Copia (CC) de TODOS los correos del flujo
    'monitor' => $emails('ORDENES_MONITOR_EMAILS', 'residentedeobra@inverconstruccion.com'),

    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

];
