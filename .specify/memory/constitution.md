# Seguimiento de Pedidos por Liberar — Constitución

<!--
  Proyecto: Seguimiento-pedidos (Levapan — Exportaciones)
  Reemplazo web del MVP en Excel "Pedidos-Despachos de Venta EXP.xlsm".
-->

## Principios Fundamentales

### I. Stack fijo (Laravel + Livewire)

La aplicación se construye sobre **Laravel 12 / PHP 8.3**, con **Laravel Breeze (stack
Livewire + Volt) + Livewire 3 + Blade + Tailwind CSS v3 + Vite 7** como capa de presentación.
No se introducen SPAs ni frameworks JS de cliente adicionales (Inertia, Vue, React). El
objetivo es una app server-rendered simple, con interactividad vía Livewire, mantenible por un
equipo pequeño.

> Enmienda 2026-08-28 (v1.1.0): el stack original era "Blade sin Livewire, Tailwind v4". Por
> decisión del propietario se adopta Breeze + Livewire 3 (Volt); Breeze fija Tailwind v3.

### II. El ERP es de sólo lectura

La aplicación **nunca escribe** al ERP (Oracle vía SQL Server `panalsrvdb`/`panalsas` /
linked server `LOGGROPDN`). Los datos de negocio (`PedidosVenta`, `Clientes`, `Parametros`)
entran por una de **dos fuentes intercambiables** (`config/erp.php` → `ERP_FUENTE`), y en
ambos casos aterrizan en **tablas espejo propias** en MySQL (`lineas_pedido`, `clientes`,
`parametros_item`):

1. **`excel`** — `php artisan erp:importar <archivo.xlsx>` (o el botón "Importar ERP").
2. **`directo`** — `php artisan erp:sincronizar`: conexión de **sólo lectura** `erp`
   (SQL Server) que corre la misma consulta `OPENQUERY(LOGGROPDN, …)` del Power Query
   original. `clientes` y `parametros_item` siguen viniendo del Excel (en el libro son hojas
   manuales, no salen de SQL Server).

La escritura de `lineas_pedido` la centraliza `App\Services\Erp\LineasPedidoSync` para las dos
fuentes. Toda la data operativa que genera la app (seguimiento, estados, fechas, usuarios) vive
en tablas propias y jamás se propaga de vuelta al ERP.

> Enmienda 2026-08-28 (v1.2.0): la v1.1.0 decía "sin conexión directa al ERP". Por decisión del
> propietario se admite la conexión `erp` **de sólo lectura** como fuente alternativa.

### III. Importación idempotente

Re-ejecutar la importación con el mismo archivo deja la base en el mismo estado. Los cargues
son **upsert por clave natural**: `(pedido, item)` para líneas de pedido, `nit_cliente` para
clientes, `item` para parámetros. Una importación registra su origen y marca de tiempo, y no
duplica filas ni pierde el seguimiento ya capturado para líneas que siguen existiendo.

### IV. Reglas de negocio con pruebas primero (NO NEGOCIABLE)

Las reglas de cálculo se cubren con pruebas automatizadas **antes** de construir la UI:

- Fórmula de toneladas: `cantidad_pedida × factor_kg / 1000`.
- Agregados por pedido: cantidad total, toneladas totales, fecha de solicitud (`MIN`).
- Filtros del universo: `estado ∈ {PL, PP}` y `item NOT LIKE '7%'`.
- Derivados de seguimiento: `alerta` (semáforo) y `comentario_correo`.

Ciclo rojo → verde → refactor. Ninguna regla de negocio se da por correcta sin una prueba
que la fije con datos representativos del dominio.

### V. Autorización por permiso, no por rol

Rutas y acciones se protegen con **permisos nombrados** (`can:pedidos.ver`,
`can:seguimiento.editar`, etc.), nunca comprobando el nombre del rol en el código. Los roles
son sólo agrupaciones de permisos administrables. El catálogo de permisos es fijo y se siembra;
no se edita desde la UI. Se usa `spatie/laravel-permission` como implementación.

### VI. Dominio en español

Migraciones, nombres de tablas y columnas, modelos, enums, rutas, vistas y textos de UI se
escriben en **español**, alineados con el vocabulario del negocio ("pedido", "línea",
"destino", "toneladas", "estado de materiales", "fecha tentativa de cargue"). El código de
framework (métodos, clases de Laravel) permanece en su idioma nativo.

### VII. Trazabilidad

Todo cambio de seguimiento registra **quién** (usuario autenticado) y **cuándo** (marca de
tiempo). No hay ediciones anónimas. El seguimiento es de dos niveles: pedido
(`estado_seguimiento`, `fecha_tentativa_cargue`) y línea (`estado_materiales`, `observaciones`,
`fecha_programa`, `inventario`).

## Restricciones Técnicas

- **Base de datos**: MySQL 8 (`DB_CONNECTION=mysql`). SQLite sólo para la suite de pruebas.
- **Fuente ERP `directo`** (opcional): conexión `erp` (`driver sqlsrv`) → requiere la extensión
  PHP `pdo_sqlsrv`. El SQL vive en `App\Support\ConsultasErp`.
- **Importación de Excel**: `maatwebsite/excel` / `PhpSpreadsheet\IOFactory` directo.
- **Roles/permisos**: `spatie/laravel-permission`.
- **Autenticación**: **Laravel Breeze** (stack Livewire + Volt). Sin auto-registro ni
  "olvidé mi contraseña" (rutas removidas); los usuarios los crea un administrador.
  `App\Livewire\Forms\LoginForm` chequea `activo`.
- **Correo**: la app genera el **cuerpo del correo de cargues** (HTML + texto plano) mediante
  un Mailable/vista Blade; no automatiza el envío ni depende de Outlook.
- **Pruebas**: PHPUnit 11 (`php artisan test`), componentes con `Livewire\Volt\Volt::test()`.
  Estilo con `laravel/pint`.

## Flujo de Desarrollo

1. Toda funcionalidad nace como una feature en `specs/NNN-nombre/` con `spec.md` + `plan.md`
   antes de escribir código.
2. `tasks.md` desglosa el trabajo por historia de usuario; cada historia es entregable de
   forma independiente (MVP incremental).
3. Las reglas de negocio (Principio IV) llevan pruebas antes que implementación.
4. Cada PR verifica: `php artisan test` en verde y `vendor/bin/pint --test` sin cambios.
5. Migraciones siempre reversibles; los seeders de catálogo (permisos, roles) son
   idempotentes.

## Gobernanza

Esta constitución prevalece sobre otras prácticas. Cualquier desviación (por ejemplo,
introducir un framework JS, escribir al ERP, o autorizar por rol en vez de por permiso) debe
justificarse en la sección **Complexity Tracking** del `plan.md` de la feature y aprobarse
explícitamente. Las enmiendas se documentan aquí con incremento de versión y fecha.

**Versión**: 1.2.0 | **Ratificada**: 2026-08-28 | **Última enmienda**: 2026-08-28
