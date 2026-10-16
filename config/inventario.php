<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inventario de exportación (SyncBox, Postgres)
    |--------------------------------------------------------------------------
    |
    | Existencias de bodega por código de producto, de SÓLO LECTURA, traídas
    | con `php artisan inventario:sincronizar` (y el botón "Sincronizar
    | inventario" de la página /inventario). El código se cruza contra
    | `item` para calcular cuánto hay disponible por repartir entre los
    | pedidos pendientes que comparten ese ítem.
    |
    */

    /*
    | Nombre de la conexión de base de datos (config/database.php). Debe
    | apuntar a Postgres `syncboxi40`.
    */
    'conexion' => env('INVENTARIO_CONEXION', 'inventario'),

    /*
    | Minutos de caché para el resultado de la consulta directa (0 = sin caché).
    */
    'cache_minutos' => (int) env('INVENTARIO_CACHE_MINUTOS', 0),

    /*
    |--------------------------------------------------------------------------
    | Modo de consulta
    |--------------------------------------------------------------------------
    |
    | 'db'  -> conexión directa a Postgres (conexión 'inventario' de arriba).
    |          Sólo funciona dentro de la red interna (192.168.80.x).
    | 'api' -> consulta vía HTTP al proyecto puente `api-datos-panal`
    |          (192.168.80.52), que sí tiene acceso a esa red. Se usa cuando
    |          la app corre fuera de la red interna (ej. Hostinger).
    |
    */
    'modo' => env('INVENTARIO_MODO', 'db'),

    'api_url' => env('INVENTARIO_API_URL'),

    'api_token' => env('INVENTARIO_API_TOKEN'),

];
