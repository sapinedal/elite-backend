<?php

/*
|--------------------------------------------------------------------------
| Editor de documentos en el navegador (ONLYOFFICE Docs)
|--------------------------------------------------------------------------
|
| ONLYOFFICE Docs corre como un servicio aparte (Docker). El navegador carga el editor desde `url`;
| el servidor de documentos descarga el archivo desde Spaces con una URL firmada y, al guardar,
| llama al callback de esta API, que sube la nueva versión a Spaces.
| Toda la comunicación va firmada con `jwt_secret` (el mismo JWT_SECRET del contenedor).
|
*/

return [

    // URL pública del servidor de documentos, ej. https://docs.inverconstruccion.com (sin barra final).
    // Vacía = el editor está deshabilitado y los archivos solo se pueden previsualizar o descargar.
    'url' => rtrim((string) env('ONLYOFFICE_URL', ''), '/'),

    // Secreto compartido con el contenedor (variable JWT_SECRET de ONLYOFFICE)
    'jwt_secret' => env('ONLYOFFICE_JWT_SECRET'),

    // URL con la que el servidor de documentos llega a esta API para el callback de guardado
    'callback_base_url' => rtrim((string) env('ONLYOFFICE_CALLBACK_URL', env('APP_URL', '')), '/'),

    // Tamaño máximo de archivo que se abre en el editor
    'max_file_mb' => (int) env('ONLYOFFICE_MAX_FILE_MB', 50),

    // Tema de la interfaz del editor (ver deploy/onlyoffice/themes)
    'ui_theme' => env('ONLYOFFICE_UI_THEME', 'theme-elite'),

    // Versiones anteriores que se conservan por archivo (carpeta oculta .versiones junto al archivo)
    'keep_versions' => (int) env('ONLYOFFICE_KEEP_VERSIONS', 20),

];
