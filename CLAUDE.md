# CLAUDE.md — Seguimiento de Pedidos por Liberar

Contexto para agentes de código. La especificación completa vive en `specs/` (Spec Kit).

## Qué es este proyecto

Aplicación web (Levapan — Exportaciones) que reemplaza el MVP en Excel
`Pedidos-Despachos de Venta EXP.xlsm`. Consulta los pedidos de venta de exportación en estado
**PL/PP** ("por liberar"), calcula **toneladas** y totales, y permite a planeación registrar el
seguimiento de liberación (estado de materiales, observaciones, fecha programada, inventario a
nivel de línea; estado y fecha tentativa de cargue a nivel de pedido) con un panel tipo semáforo
y la generación del correo de cargues. Incluye un módulo de
administración de usuarios, roles y permisos.

## Stack

- **Laravel 12** · **PHP 8.3** · **MySQL 8** (`DB_CONNECTION=mysql`, base `seguimiento_pedidos`).
- **Laravel Breeze (stack Livewire + Volt, clases)** · **Livewire 3** · **Blade + Tailwind CSS v3 + Vite 7**.
  Toda la UI son componentes **Volt** en `resources/views/livewire/**`, cargados con `Volt::route()`.
- **UI kit propio**: componentes `<x-ui.page>` (encabezado + ancho `max-w-screen-2xl` + flash),
  `<x-ui.card>`, `<x-ui.badge color="…">`, y clases utilitarias en `resources/css/app.css`
  (`.data-table` con encabezado sticky, `.field` / `.field-sm`, `.btn-primary` / `.btn-secondary`
  / `.btn-dark` / `.btn-link`, `.kv-label` / `.kv-value`, `.num` para columnas numéricas).
- `maatwebsite/excel` instalado; la importación de Excel usa `PhpSpreadsheet\IOFactory` directo.
- **Fuente ERP conmutable** (`config/erp.php` → `ERP_FUENTE`): `excel` (`erp:importar`) o
  `directo` (`erp:sincronizar`, conexión `erp` sqlsrv de sólo lectura). Ver `specs/001-.../erp-fuentes.md`.
- `spatie/laravel-permission` para roles/permisos. Auth de Breeze + chequeo de `activo` en
  `App\Livewire\Forms\LoginForm`. Sin registro público ni recuperación de clave (los crea el admin).
- Pruebas: **PHPUnit 11** (`php artisan test`), componentes con `Livewire\Volt\Volt::test()`. Estilo: `laravel/pint`.
- Idioma del dominio: **español** (tablas, columnas, rutas, enums, UI).

## Reglas de negocio clave

- Universo "por liberar": líneas con `estado ∈ {PL, PP}` **y** `item NOT LIKE '7%'` (y `activo`).
- **Toneladas de línea** = `cantidad_pedida × parametros_item.factor_kg / 1000`.
- **Cantidad total** del pedido = `Σ cantidad_pedida` (unidades de venta).
- **Toneladas totales** = `Σ (cantidad_pedida × factor_kg) / 1000`.
- **Fecha de solicitud** = `MIN(fecha_elaboracion)` de las líneas del pedido.
- **Destino** = `clientes.destino` cruzando por `nit_cliente` (texto exacto); sin match → `#N/D`.
- Ítem sin `factor_kg` → toneladas nulas + marca "sin factor"; total del pedido "incompleto".
- **Inventario** (`seguimientos.inventario`, editable por línea, igual que estado de materiales
  y observaciones) → **% de cumplimiento** de la línea = `inventario / cantidad_pedida × 100`
  (null si no hay cantidad). A nivel de pedido: `Σ inventario / Σ cantidad_pedida × 100`.
- **Filtro por parámetro** (`parametros_item.categoria`): vive en el **detalle del pedido**
  (`pedidos/show`), filtra las líneas de ese pedido; NO está en el listado general.
- El **ERP es de sólo lectura**. `lineas_pedido` se refresca por `erp:importar <ruta.xlsx>`
  (fuente `excel`) o `erp:sincronizar` (fuente `directo`, SQL Server); ambos vía
  `App\Services\Erp\LineasPedidoSync` (idempotente, upsert por `(pedido,item)`, "todas
  inactivas → upsert", aborta si el origen da 0 filas, no toca las tablas de seguimiento).
  `clientes` y `parametros_item` siempre salen del Excel.
- **Autorización por permiso, no por rol**: rutas con `can:<permiso>`; los componentes Livewire
  revalidan con `abort_unless(... ->can(...))` en cada acción.
- Todo cambio de seguimiento registra `actualizado_por` + timestamp.

## Modelo de seguimiento (dos niveles)

- **Nivel PEDIDO** (`seguimiento_pedidos`, modelo `SeguimientoPedido`): `estado_seguimiento`
  (`Pendiente` / `En validación` / `Listo para cargar`) y `fecha_tentativa_cargue`.
  Derivados: `alerta` (`🔴/🟡/✅`) y `comentario_correo`
  (sin fecha → `"PENDIENTE POR DEFINIR FECHA"`; con fecha → `"Pedido disponible para cargue sobre el dd/mm/aaaa"`).
- **Nivel LÍNEA** (`seguimientos`, modelo `Seguimiento`): `estado_materiales` (select cerrado,
  enum `EstadoMateriales`: `PEND. MATERIAL DE EMPAQUE` / `PEND. MATERIAL MATERIA PRIMA` /
  `PEND. REQUISICION` / `PEND. ORDEN DE COMPRA` / `OC ENVIADA A PROVEEDOR`), `observaciones`
  (texto libre; antes `observaciones_plan`), `fecha_programa` (date), `inventario`.
  Derivado: `porcentaje_cumplimiento` = `inventario / cantidad × 100`.
- Estado de revisión del pedido: `REVISADO` si existe fila en `seguimiento_pedidos`, si no `POR REVISAR`.
- El **correo de cargues** lista los pedidos con `seguimiento_pedidos.fecha_tentativa_cargue`.

## Catálogo de permisos

`pedidos.ver` · `seguimiento.editar` · `correo.generar` · `erp.importar` · `admin.usuarios` ·
`admin.roles`

Roles base sembrados: `Administrador` (todos, protegido), `Planeador`
(`pedidos.ver`, `seguimiento.editar`, `correo.generar`), `Consulta` (`pedidos.ver`).

## Datos fuente (referencia)

- `Q:\Consulta SQL Pedidos-Despachos.xlsx` — hojas `PedidosVenta` (líneas de pedido del ERP),
  `Clientes` (NIT → cliente → destino/país), `Parametros` (item → `Factor kg` + embalaje).
  La consulta original es Power Query → SQL Server `panalsrvdb`/`panalsas` →
  `OPENQUERY(LOGGROPDN, ...)` contra Oracle (compañía `'01'`, `pedtipocons='0542'`,
  `peeclasdocu='PEVE'`). La variante "Pedidos PL" añade `ESTADO in ('PL','PP')` e
  `ITEM not like '7%'`.
- `Q:\Pedidos-Despachos de Venta EXP.xlsm` — MVP funcional. Hojas `Pedidos Liberar`,
  `Liberación_Pedido` (captura), `BD_Seguimiento` (historial + `ALERTA` + `COMENTARIO CORREO`),
  `Cargues_Correo` (+ macro `PrepararCorreo`). Macros `RegistrarPedido` / `CargarHistorial`
  sincronizan los 3 campos de seguimiento.

## Estructura de especificación (Spec Kit)

```
.specify/memory/constitution.md          Principios del proyecto (v1.2.0)
specs/001-seguimiento-pedidos-liberar/    Módulo principal (spec, plan, data-model, contracts, tasks)
  └─ erp-fuentes.md                       Fuente 'excel' vs 'directo' (SQL Server) — arquitectura y setup
specs/002-admin-roles-permisos/           Auth + usuarios/roles/permisos
```

Skills disponibles: `/speckit-specify`, `/speckit-plan`, `/speckit-tasks`, `/speckit-implement`,
`/speckit-clarify`, `/speckit-analyze`, `/speckit-checklist`.

## Comandos útiles

```bash
php artisan migrate
php artisan db:seed --class=RolesPermisosSeeder
php artisan erp:importar "Q:\Consulta SQL Pedidos-Despachos.xlsx"   # fuente 'excel'
php artisan erp:sincronizar                                          # fuente 'directo' (SQL Server)
php artisan test
vendor/bin/pint --test
npm run build
```

## Componentes Volt (rutas)

| Ruta | Componente (`resources/views/livewire/…`) | Permiso |
|---|---|---|
| `GET /login` | `pages/auth/login` (Breeze) | — |
| `GET /pedidos-liberar` | `pedidos/index` (barra de filtros compacta 1 fila + paginación + botón Importar/Sincronizar ERP) | `pedidos.ver` |
| `GET /pedidos-liberar/{pedido}` | `pedidos/show` (form pedido + filtro por categoría + tabla líneas paginada con select estado materiales / observaciones / fecha programada / inventario) | `pedidos.ver` |
| `GET /cargues/correo` | `cargues/correo` | `correo.generar` |
| `GET /admin/usuarios` | `admin/usuarios/index` (CRUD + reset clave) | `admin.usuarios` |
| `GET /admin/roles` | `admin/roles/index` (CRUD + permisos) | `admin.roles` |
| `GET /admin/permisos` | `admin/permisos/index` (matriz sólo lectura) | `admin.roles` |

`GET /dashboard` → redirige a `pedidos.index` (Breeze lo usa tras el login).

## Estado de implementación (2026-08-28)

Ambas features **implementadas y verificadas** contra el Excel real y MySQL, sobre
**Breeze + Livewire 3 (Volt)**. `php artisan test` → **75 passed**. `vendor/bin/pint --test` → passed.

Añadido después: fuente ERP `directo` (SQL Server, `erp:sincronizar`) con `LineasPedidoSync`
compartido — implementado y con pruebas (filas del ERP simuladas), **sin probar contra el
servidor real** (falta `pdo_sqlsrv` + red). Barra de filtros de la lista compactada a una fila.
`estado_materiales` pasó a select cerrado; `observaciones_plan` → `observaciones`; nuevo
`fecha_programa` (date) por línea.

Puesta en marcha desde cero:

```bash
composer install
php artisan migrate:fresh --seed        # tablas + permisos/roles/admin
php artisan erp:importar "Q:\Consulta SQL Pedidos-Despachos.xlsx"
npm install && npm run build
php artisan serve                       # login: admin@levapan.com / cambiar123
```

Verificado en vivo: import (15/244/1947, idempotente); lista con **filtro por parámetro
(categoría)**, columnas **Inventario** y **% Cumpl.**, paginación; detalle del pedido 179
(cantidad total 47.550, toneladas de línea `2400×0.549125/1000 = 1,31790`); seguimiento de
**pedido** (estado → ✅, fecha tentativa) y de **línea** (inventario 4800 → % cumpl. 50%);
correo de cargues; CRUD de usuarios/roles.

### Desviaciones respecto a las specs

- **Frontend**: la spec/constitución decían "Blade + Tailwind 4, sin Livewire". El usuario
  pidió después **Breeze + Livewire 3 (Volt)**; Breeze 2.4 baja Tailwind a **v3**. Toda la UI
  son componentes Volt; el admin también (no controladores).
- **Seguimiento en dos niveles**: `fecha_tentativa_cargue`, `estado_seguimiento` y `alerta`
  pasaron a **nivel de pedido** (`seguimiento_pedidos`). `estado_materiales`,
  `observaciones_plan` e **`inventario`** quedan a nivel de línea (`seguimientos`).
  Nuevo derivado `porcentaje_cumplimiento` (= `cantidad_pedida / inventario`).
- **Importación**: `PhpSpreadsheet\IOFactory` directo en `App\Services\ImportadorErp`
  (upsert transaccional, `item`/`nit_cliente` como texto, saneo de `#REF!`).
  `maatwebsite/excel` queda instalado.
- **Fuente ERP `directo`**: la constitución v1.2.0 admite la conexión `erp` (sqlsrv) de sólo
  lectura como alternativa al Excel. La escritura de `lineas_pedido` se comparte en
  `App\Services\Erp\LineasPedidoSync`; el SQL en `App\Support\ConsultasErp`. Detalle en
  `specs/001-.../erp-fuentes.md`.
- **Rol**: `App\Models\Role` extiende el de spatie sólo para castear `protegido` a bool;
  `config/permission.php` apunta a ese modelo.
- **Entorno**: `extension=zip` habilitado en el `php.ini` de Laragon (backup `php.ini.bak-claude`).

## Orden de implementación (referencia histórica)

1. `002-admin-roles-permisos` (seeder + login) — la feature 001 depende de sus permisos.
2. `001-seguimiento-pedidos-liberar` (importación → lista → detalle/toneladas → captura →
   semáforo → correo).
