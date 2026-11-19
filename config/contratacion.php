<?php

return [

    // Carpeta del bucket donde viven las carpetas de contratos de cada proyecto (Raíz -> tecnica)
    's3_root' => env('CONTRATACION_S3_ROOT', 'tecnica'),

    // Días antes del vencimiento en que se avisa cada póliza (una vez en cada umbral): a los 40, 30 y 20 días
    'policy_alert_thresholds' => $thresholds = collect(explode(',', (string) env('CONTRATACION_POLICY_ALERT_THRESHOLDS', '40,30,20')))
        ->map(fn ($days) => (int) trim($days))->filter(fn ($days) => $days > 0)->unique()->sortDesc()->values()->all(),

    // Ventana de alerta (el umbral mayor): desde aquí la póliza se marca como próxima a vencer en la aplicación
    'policy_alert_days' => max($thresholds ?: [40]),

    // Las alertas de pólizas llegan a los usuarios con el permiso correos.contratacion.polizas asignado directamente
    // y acceso al proyecto. Esta variable es SOLO para buzones externos (separados por coma). Si no hay ninguno se
    // notifica a los usuarios con contratacion.editar del proyecto
    'alert_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('CONTRATACION_ALERT_EMAILS', ''))))),

    // URL del frontend para el enlace "Ver contratos" del correo
    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

];
