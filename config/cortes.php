<?php

// Lista desde el .env (separada por $separator). Si la variable no existe o está vacía se usa $default.
$emails = fn (string $key, string $default = '', string $separator = ',') => array_values(array_filter(array_map(
    'trim',
    explode($separator, (string) (env($key) ?: $default))
)));

return [

    // Los cortes se guardan en la carpeta de su contrato (<contrato>/Cortes/<código>). Esta carpeta solo se usa
    // para cortes sin contrato: <s3_root>/<proyecto>/<torre>/Cortes/<código>
    's3_root' => env('CORTES_S3_ROOT', 'tecnica/Cortes'),

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

    // Carga a Multifox (ERP externo): al cargar una factura se notifica a los usuarios con estos CARGOS
    // (nombre exacto del cargo en Configuración → Áreas y Cargos), para no amarrar el aviso a una persona.
    // Los cargos se separan con | porque sus nombres pueden tener comas. Si nadie tiene el cargo se usan
    // los correos de respaldo de CORTES_MULTIFOX_EMAILS.
    'multifox_positions' => $emails('CORTES_MULTIFOX_CARGOS', 'Profesional de Planeación, Control y Contratos', '|'),
    'multifox_emails' => $emails('CORTES_MULTIFOX_EMAILS'),

    // Solo se envía el aviso de Multifox a correos de estos dominios (lleva la factura adjunta)
    'multifox_allowed_domains' => $emails('CORTES_MULTIFOX_DOMINIOS', 'inverconstruccion.com'),

    // Facturas que pesen hasta este tamaño (MB) se adjuntan al correo de Multifox
    'invoice_attach_max_mb' => (int) env('CORTES_INVOICE_ATTACH_MAX_MB', 10),

    // Copia (CC) de TODOS los correos del flujo, para verificar que las notificaciones están llegando
    'monitor' => $emails('CORTES_MONITOR_EMAILS', 'residentedeobra@inverconstruccion.com'),

    // URL del frontend para el enlace "Ver corte" de los correos
    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

];
