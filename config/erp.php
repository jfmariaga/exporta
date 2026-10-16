<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fuente de datos del ERP
    |--------------------------------------------------------------------------
    |
    | 'excel'   -> se cargan las líneas con `php artisan erp:importar <archivo>`
    |              (y por el botón "Importar ERP" de la lista de pedidos).
    | 'directo' -> se consultan en vivo desde SQL Server con
    |              `php artisan erp:sincronizar` (y el botón "Sincronizar ERP").
    |
    | En ambos casos el ERP es de SÓLO LECTURA y los datos quedan en las tablas
    | espejo `lineas_pedido` / `clientes` / `parametros_item`.
    | `clientes` y `parametros_item` siempre provienen del Excel (en el libro
    | original esas hojas son manuales, no salen de SQL Server).
    |
    */

    'fuente' => env('ERP_FUENTE', 'excel'),

    /*
    | Nombre de la conexión de base de datos (config/database.php) usada por la
    | fuente 'directo'. Debe apuntar a SQL Server `panalsrvdb` / db `panalsas`.
    */
    'conexion' => env('ERP_CONEXION', 'erp'),

    /*
    | Minutos de caché para el resultado de la consulta directa (0 = sin caché).
    | Útil si se dispara la sincronización con mucha frecuencia.
    */
    'cache_minutos' => (int) env('ERP_CACHE_MINUTOS', 0),

    /*
    |--------------------------------------------------------------------------
    | Modo de la fuente 'directo'
    |--------------------------------------------------------------------------
    |
    | 'db'  -> conexión directa a SQL Server (conexión 'erp' de arriba). Sólo
    |          funciona si la app corre dentro de la red interna (192.168.80.x).
    | 'api' -> consulta vía HTTP al proyecto puente `api-datos-panal`
    |          (192.168.80.52), que sí tiene acceso a esa red. Se usa cuando
    |          la app corre fuera de la red interna (ej. Hostinger).
    |
    */
    'modo' => env('ERP_MODO', 'db'),

    'api_url' => env('ERP_API_URL'),

    'api_token' => env('ERP_API_TOKEN'),

];
