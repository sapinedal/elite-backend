<?php

return [

    // Carpeta del bucket donde viven las carpetas de contratos de cada proyecto (Raíz -> tecnica)
    's3_root' => env('CONTRATACION_S3_ROOT', 'tecnica'),

    // Días de anticipación para alertar el vencimiento de la vigencia de una póliza
    'policy_alert_days' => (int) env('CONTRATACION_POLICY_ALERT_DAYS', 40),

    // Destinatarios de las alertas de pólizas (separados por coma). Si se configuran se usan SOLO estos;
    // si quedan vacíos se notifica a los usuarios con permiso contratacion.editar asignados al proyecto
    'alert_emails' => array_values(array_filter(array_map('trim', explode(',', (string) (
        env('CONTRATACION_ALERT_EMAILS') ?: 'residentedeobra@inverconstruccion.com,dirobrasanmiguel@inverconstruccion.com'
    ))))),

    // URL del frontend para el enlace "Ver contratos" del correo
    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

];
