# Fuentes de datos del ERP

El universo "pedidos por liberar" siempre se lee desde las **tablas espejo** en MySQL
(`lineas_pedido`, `clientes`, `parametros_item`). Lo que cambia es **cómo se llenan**.
Se elige con `config/erp.php` → `ERP_FUENTE` (`.env`).

| `ERP_FUENTE` | Cómo se refresca `lineas_pedido` | `clientes` / `parametros_item` |
|---|---|---|
| `excel` (default) | `php artisan erp:importar "<ruta.xlsx>"` · botón **Importar ERP** | del mismo Excel |
| `directo` | `php artisan erp:sincronizar` · botón **Sincronizar ERP** · tarea agendada | del Excel (hojas manuales) |

En ambos casos:

- El ERP es de **sólo lectura** (constitución, Principio II). La app nunca escribe a Oracle/SQL Server.
- El refresco es **idempotente**: todas las filas se marcan `activo=false` y el `upsert` por
  `(pedido, item)` reactiva las que vienen del origen; las ausentes quedan inactivas (histórico).
- **Nunca** se tocan `seguimientos` ni `seguimiento_pedidos`.
- Si el origen devuelve 0 filas, el proceso **aborta** sin vaciar la tabla.

## Arquitectura

```
                         ┌─────────────────────────────┐
  erp:importar  ───────▶ │ App\Services\ImportadorErp   │ (Excel: 3 hojas)
                         └───────────────┬─────────────┘
                                         │  registros normalizados
  erp:sincronizar ─────▶ ┌───────────────▼─────────────┐
                         │ App\Services\Erp\           │
                         │   ConsultaDirectaErp         │ (SQL Server: OPENQUERY)
                         └───────────────┬─────────────┘
                                         │
                         ┌───────────────▼─────────────┐
                         │ App\Services\Erp\           │  transacción + "todas inactivas"
                         │   LineasPedidoSync           │  + upsert (pedido,item) + recuento
                         └───────────────┬─────────────┘
                                         ▼
                                   lineas_pedido
```

- **`LineasPedidoSync`** — único punto de escritura de `lineas_pedido`; también expone los
  normalizadores compartidos (`codigoItem`, `textoNit`, `aNumero`, `aFecha`).
- **`ImportadorErp`** — lee el `.xlsx` (`PhpSpreadsheet\IOFactory`), mapea encabezados,
  refresca `clientes` y `parametros_item`, y delega las líneas a `LineasPedidoSync`.
- **`ConsultaDirectaErp`** — corre `App\Support\ConsultasErp::pedidosPl()` sobre la conexión
  `erp`, mapea los alias del ERP a las columnas de la tabla y delega a `LineasPedidoSync`.
  El método `filas()` está aislado para poder testear sin SQL Server.

## Modo `directo`: puesta en marcha

1. **Habilitar el driver** en el PHP de Laragon (`php.ini`):
   ```ini
   extension=pdo_sqlsrv
   extension=sqlsrv        ; opcional; pdo_sqlsrv basta para Eloquent
   ```
   Verificar: `php -m | findstr sqlsrv`. (Requiere el "Microsoft ODBC Driver for SQL Server".)

2. **Configurar la conexión** en `.env`:
   ```env
   ERP_FUENTE=directo
   ERP_HOST=panalsrvdb
   ERP_PORT=1433
   ERP_DATABASE=panalsas
   ERP_USERNAME=<usuario de sólo lectura>
   ERP_PASSWORD=<clave>
   ERP_ENCRYPT=yes
   ERP_TRUST_SERVER_CERTIFICATE=true
   ```
   La conexión `erp` ya está definida en `config/database.php`.

3. **Probar**:
   ```bash
   php artisan erp:importar "Q:\Consulta SQL Pedidos-Despachos.xlsx"   # 1 vez: carga clientes/parámetros
   php artisan erp:sincronizar                                          # trae las líneas en vivo
   ```

4. **Agendar** (opcional) — ya está en `routes/console.php`, activo sólo si `ERP_FUENTE=directo`:
   ```php
   Schedule::command('erp:sincronizar')->everyThirtyMinutes()->withoutOverlapping()->runInBackground();
   ```
   Necesita `php artisan schedule:work` (o un cron a `schedule:run`).

## La consulta (`App\Support\ConsultasErp::pedidosPl()`)

Es la misma consulta **"Pedidos PL"** del Power Query del archivo
`Q:\Consulta SQL Pedidos-Despachos.xlsx`:

- Conexión SQL Server `panalsrvdb` / db `panalsas`.
- `SELECT * FROM OPENQUERY(LOGGROPDN, '<SQL Oracle>')` — el linked server `LOGGROPDN` federa a Oracle.
- Filtros de negocio (empujados a Oracle): compañía `'01'`, `pedtipocons='0542'`,
  `peeclasdocu='PEVE'`, `pedfechelab > 2024-10-01`, **`eobcodigo IN ('PL','PP')`** y
  **`pedcodiitem NOT LIKE '7%'`**.
- Devuelve una fila por línea con los alias: `pedido, bodega, item, descripcion, grupoitem,
  fechaelaboracion, unidadmedida, cantidadpedida, cantidaddespachada, fecharequerida,
  fechadespacho, preciototalneto, nitcliente, nombrecliente, estado, fechaultimomovimiento, usuario`.

## Limitaciones y decisiones

- **`clientes` y `parametros_item`** no salen de SQL Server (en el libro son hojas manuales
  `Tabla3` / `Tabla4`). En modo `directo` se siguen refrescando con `erp:importar` o se
  mantienen a mano. Si más adelante se identifican tablas Oracle equivalentes, se agregan a
  `ConsultasErp` y a `ConsultaDirectaErp`.
- **Sin joins entre bases**: el seguimiento vive en MySQL y el ERP en SQL Server. Por eso el
  modo `directo` **materializa** las líneas en `lineas_pedido` (staging) en lugar de consultar
  en vivo en cada request; así `PedidosPorLiberarQuery` y `CorreoCarguesBuilder` siguen
  resolviendo todo en un solo motor.
- En modo `directo` la tabla `lineas_pedido` sólo contiene filas `PL`/`PP` (la consulta ya
  filtra); en modo `excel` contiene todos los estados de la hoja. La app sólo mira el universo
  `porLiberar()`, así que el comportamiento visible es el mismo.
- `pdo_sqlsrv` no está instalado en el entorno de desarrollo actual; el modo `directo` está
  **implementado y cubierto por pruebas** (`tests/Feature/SincronizarErpTest.php`, con las
  filas del ERP simuladas) pero **no probado contra el servidor real**.
