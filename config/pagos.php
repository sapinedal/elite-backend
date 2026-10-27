<?php

// Lista desde el .env (separada por $separator). Si la variable no existe o está vacía se usa $default.
$list = fn (string $key, string $default = '', string $separator = ',') => array_values(array_filter(array_map(
    'trim',
    explode($separator, (string) (env($key) ?: $default))
)));

return [

    // Cargos que priorizan y programan los pagos (Configuración → Áreas y Cargos), separados con |.
    // Quien tenga el permiso pagos.priorizar también puede hacerlo.
    'priorizar_positions' => $list('PAGOS_PRIORIZAR_CARGOS', 'Profesional de Planeación, Control y Contratos', '|'),

    // Frente para las cuentas sin torre (costos indirectos), como la primera sección del cuadro de pagos
    'frente_indirectos' => env('PAGOS_FRENTE_INDIRECTOS', 'INDIRECTOS'),

    // Frentes adicionales para el cargue manual (además de las torres de cada proyecto)
    'frentes_extra' => $list('PAGOS_FRENTES_EXTRA', 'TANQUE|PORTERIA', '|'),

    // Carpeta S3 de los soportes de pago de cuentas manuales (los de cortes y órdenes van en su carpeta)
    's3_root' => env('PAGOS_S3_ROOT', 'tecnica/Cuentas por pagar'),

    'max_upload_mb' => (int) env('PAGOS_MAX_UPLOAD_MB', 50),

];
