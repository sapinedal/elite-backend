<?php

return [

    // Carpeta del bucket donde Contabilidad carga las facturas: <s3_root>/<MES AÑO>, ej. contabilidad/OCTUBRE 2026.
    // Los soportes del residente quedan en <s3_root>/<MES AÑO>/Soportes/<documento>
    's3_root' => env('FACTURACION_S3_ROOT', 'contabilidad'),

    // Tamaño máximo de cada factura o soporte (MB); se sube directo a S3 con URL firmada
    'max_upload_mb' => (int) env('FACTURACION_MAX_UPLOAD_MB', 100),

];
