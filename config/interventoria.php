<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Informes semanales de interventoría (Módulo Técnico)
    |--------------------------------------------------------------------------
    |
    | Equiproyectos sube cada semana "INFORME DE INTERVENTORÍA SEMANA N.pdf" a Drive y rclone lo copia a Spaces.
    | Elite toma siempre el de mayor número de semana, lo lee con tools/interventoria/parse_informe.py
    | (Python + pdfplumber) y guarda los datos para la pantalla de Interventoría.
    |
    */

    // Disco y carpeta donde llegan los informes (la misma que ve Gestión Documental)
    'disk' => env('INTERVENTORIA_DISK', 's3'),
    'folder' => env('INTERVENTORIA_FOLDER', 'tecnica/INFORMES SEMANALES INTERVENTORÍA'),

    // Intérprete de Python con pdfplumber instalado (entorno aislado en el servidor, ver tools/interventoria/README.md)
    'python' => env('INTERVENTORIA_PYTHON', '/opt/elite-pdf/venv/bin/python'),
    'parser' => base_path('tools/interventoria/parse_informe.py'),

    // Segundos máximos para leer un informe
    'timeout' => (int) env('INTERVENTORIA_TIMEOUT', 120),

];
