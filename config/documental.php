<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Caché del módulo documental
    |--------------------------------------------------------------------------
    |
    | Store usado para cachear listados del bucket y reglas de carpetas. Se usa un
    | store local (file / redis) porque el store "database" implica una ida y vuelta
    | a la base de datos por cada lectura, lo que anula el beneficio de la caché.
    |
    */

    'cache_store' => env('DOCUMENTAL_CACHE_STORE', 'file'),

    // Segundos que se conserva el listado de una carpeta. Los cambios hechos desde la app
    // invalidan la caché al instante; los hechos por fuera (ej. rclone) se ven al expirar o con "Actualizar".
    'listing_ttl' => (int) env('DOCUMENTAL_LISTING_TTL', 300),

    // Búsqueda en subcarpetas: segundos que se conserva el índice del bucket y máximo de resultados por tipo.
    // El índice se reconstruye al expirar, con "Actualizar" o con `php artisan documental:index` (programado cada 10 min).
    'search_index_ttl' => (int) env('DOCUMENTAL_SEARCH_INDEX_TTL', 900),
    'search_max_results' => (int) env('DOCUMENTAL_SEARCH_MAX_RESULTS', 300),

    // Registrar en auditoría los intentos de acciones negadas por permisos
    'log_denied_attempts' => (bool) env('DOCUMENTAL_LOG_DENIED', true),

];
