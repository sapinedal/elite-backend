<?php

return [

    // Carpeta del bucket donde viven las carpetas de contratos de cada proyecto (Raíz -> tecnica)
    's3_root' => env('CONTRATACION_S3_ROOT', 'tecnica'),

    // Días antes del vencimiento en que se avisa cada póliza (una vez en cada umbral): a los 40, 30 y 20 días
    'policy_alert_thresholds' => $thresholds = collect(explode(',', (string) env('CONTRATACION_POLICY_ALERT_THRESHOLDS', '40,30,20')))
        ->map(fn ($days) => (int) trim($days))->filter(fn ($days) => $days > 0)->unique()->sortDesc()->values()->all(),

    // Ventana de alerta (el umbral mayor): desde aquí la póliza se marca como próxima a vencer en la aplicación
    'policy_alert_days' => max($thresholds ?: [40]),

    // Destinatarios de las alertas de pólizas (separados por coma): Director de Obra y Paula Camacho (Planeación,
    // Control y Contratos). Además reciben el aviso los usuarios con el permiso contratacion.alertas_polizas
    // (ej. María José Ospina). Si no hay ninguno se notifica a los usuarios con contratacion.editar del proyecto
    'alert_emails' => array_values(array_filter(array_map('trim', explode(',', (string) (
        env('CONTRATACION_ALERT_EMAILS') ?: 'residentedeobra@inverconstruccion.com,dirobrasanmiguel@inverconstruccion.com'
    ))))),

    // URL del frontend para el enlace "Ver contratos" del correo
    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost')), '/'),

];
