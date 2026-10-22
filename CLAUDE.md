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
- **UI kit propio**: componentes `<x-ui.page>` (encabezado + ancho completo + flash),
  `<x-ui.card>`, `<x-ui.badge color="…">`, y clases utilitarias en `resources/css/app.css`
  (`.data-table` con encabezado sticky, variante `.data-table--compact` para tablas con muchas
  columnas, `.field` / `.field-sm`, `.btn-primary` / `.btn-secondary` / `.btn-dark` / `.btn-link`,
  `.kv-label` / `.kv-value`, `.num` para columnas numéricas). `App\Support\Numero::decimal()`
  formatea números en es-CO (coma decimal, punto de miles) recortando ceros sobrantes.
- **SweetAlert2** (`resources/js/app.js`, expuesto como `window.Swal`/`window.Toast`): reemplaza
  los `confirm()` nativos del navegador y los mensajes flash de éxito. Patrón de confirmación de
  borrado (ver `admin/*/index.blade.php`): botón con `x-data x-on:click="Swal.fire({...}).then(r
  => { if (r.isConfirmed) $wire.eliminar(id) })"`, usando `@js(...)` de Blade para los textos
  dinámicos (evita problemas de comillas). El flash `session()->flash('status', …)` ya no se
  pinta como caja verde: `<x-ui.page>` dispara un `Toast.fire(...)` vía `x-init` + `@js()`.
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
- **Reparto de inventario por ítem** (`DisponibilidadInventario::estados()`, módulo `/inventario`):
  `cantidad_total` (existencia sincronizada de SyncBox, mostrada como "Inventario") vs
  `total_pedido` (`Σ cantidad_pedida` de todas las líneas PL/PP activas de ese ítem, sin
  importar si ya se repartió) → `faltante = max(0, total_pedido - cantidad_total)`, mostrado
  como alerta roja cuando es mayor a cero. Distinto de `asignado`/`disponible`, que miden el
  reparto manual ya hecho (`Σ seguimientos.inventario`), no la demanda total.
- **Filtro por parámetro** (`parametros_item.categoria`): vive en el **detalle del pedido**
  (`pedidos/show`), filtra las líneas de ese pedido; NO está en el listado general.
- El **ERP es de sólo lectura**. `lineas_pedido` se refresca por `erp:importar <ruta.xlsx>`
  (fuente `excel`) o `erp:sincronizar` (fuente `directo`, SQL Server); ambos vía
  `App\Services\Erp\LineasPedidoSync` (idempotente, upsert por `(pedido,item)`, "todas
  inactivas → upsert", aborta si el origen da 0 filas, no toca las tablas de seguimiento).
  `clientes` y `parametros_item` son **maestros manuales**: el usuario funcional los crea,
  edita y elimina desde la UI (`/admin/clientes`, `/admin/parametros-item`); ningún comando
  de importación/sincronización los toca (ver sección "Maestros manuales" más abajo).
- **Autorización por permiso, no por rol**: rutas con `can:<permiso>`; los componentes Livewire
  revalidan con `abort_unless(... ->can(...))` en cada acción.
- Todo cambio de seguimiento registra `actualizado_por` + timestamp.

## Modelo de seguimiento (dos niveles)

- **Nivel PEDIDO** (`seguimiento_pedidos`, modelo `SeguimientoPedido`): `estado_seguimiento`
  (`Pendiente` / `En validación` / `Listo para cargar`) y `fecha_tentativa_cargue`.
  Derivados: `alerta` (`🔴/🟡/✅`) y `comentario_correo`
  (sin fecha → `"PENDIENTE POR DEFINIR FECHA"`; con fecha → `"Pedido disponible para cargue sobre el dd/mm/aaaa"`).
- **Nivel LÍNEA** (`seguimientos`, modelo `Seguimiento`): `estado_materiales` (select cerrado,
  etiqueta en la UI "Estado"; enum `EstadoMateriales`: `PEND. MATERIAL DE EMPAQUE` /
  `PEND. MATERIAL MATERIA PRIMA` / `PEND. REQUISICION` / `PEND. ORDEN DE COMPRA` /
  `OC ENVIADA A PROVEEDOR` / `OK` / `EN INVENTARIO` / `PENDIENTE INGRESO OC` /
  `DEVOLUCION CEDI` / `REPROCESO SUPER PACK`), `observaciones`
  (texto libre; antes `observaciones_plan`), `fecha_programa` (date), `inventario`.
  Derivado: `porcentaje_cumplimiento` = `inventario / cantidad × 100`.
- **Toneladas/volumen/cajas por línea** (`PedidosPorLiberarQuery`, no persistidas):
  `toneladas_linea = cantidad_pedida × factor_kg / 1000` (mostrado a 1 decimal);
  `cajas_linea = cantidad_pedida / embalaje`; `volumen_linea = cajas_linea × volumen / 1.000.000`
  (2 decimales). A nivel de pedido se suman (`toneladas_total`, `volumen_total`) y el
  volumen total sugiere contenedor: `< 32 m³` → 20', `32–60 m³` → 40', `> 60 m³` → alerta
  "Excede cubicaje". Ítem sin `embalaje`/`volumen` → volumen nulo + marca "sin volumen"
  (mismo patrón que `sin_factor`/toneladas).
- Estado de revisión del pedido: `REVISADO` si existe fila en `seguimiento_pedidos`, si no `POR REVISAR`.
- El **correo de cargues** lista los pedidos con `seguimiento_pedidos.fecha_tentativa_cargue`.

## Catálogo de permisos

`pedidos.ver` · `seguimiento.editar` · `correo.generar` · `erp.importar` · `admin.usuarios` ·
`admin.roles`

Roles base sembrados: `Administrador` (todos, protegido), `Planeador`
(`pedidos.ver`, `seguimiento.editar`, `correo.generar`), `Consulta` (`pedidos.ver`).

## Datos fuente (referencia)

- `Q:\Consulta SQL Pedidos-Despachos.xlsx` — hoja `PedidosVenta` (líneas de pedido del ERP).
  Históricamente traía también `Clientes` (NIT → cliente → destino/país) y `Parametros`
  (item → `Factor kg` + embalaje), pero esas dos ya no se importan desde aquí (ver "Maestros
  manuales" más abajo); la estructura de esas hojas sigue sirviendo de referencia para los
  campos del CRUD en la app. La consulta original es Power Query → SQL Server `panalsrvdb`/`panalsas` →
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
| `GET /materiales` | `materiales/index` (gestión por ítem: filtra líneas del universo "por liberar" por estado de materiales; sin estado seleccionado no muestra nada, ítems sin estado asignado nunca aparecen) | `pedidos.ver` |
| `GET /inventario` | `inventario/index` (reparto por ítem: Inventario, Total pedido, Faltante, Asignado, Disponible) | `seguimiento.editar` |
| `GET /inventario/detalle` | `inventario/detalle` | `seguimiento.editar` |
| `GET /admin/usuarios` | `admin/usuarios/index` (CRUD + reset clave) | `admin.usuarios` |
| `GET /admin/roles` | `admin/roles/index` (CRUD + permisos) | `admin.roles` |
| `GET /admin/permisos` | `admin/permisos/index` (matriz sólo lectura) | `admin.roles` |
| `GET /admin/clientes` | `admin/clientes/index` (CRUD de NIT → cliente → destino) | `admin.usuarios` |
| `GET /admin/parametros-item` | `admin/parametros-item/index` (CRUD de ítem → factor kg + embalaje) | `admin.usuarios` |

`GET /dashboard` → redirige a `pedidos.index` (Breeze lo usa tras el login).

## Estado de implementación (2026-08-28)

Ambas features **implementadas y verificadas** contra el Excel real y MySQL, sobre
**Breeze + Livewire 3 (Volt)**. `php artisan test` → **103 passed** (2026-10-22).
`vendor/bin/pint --test` → passed.

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

## Integración remota para Hostinger — API puente `api-datos-panal` (2026-10-16)

Al subir esta app a Hostinger, el hosting no tiene acceso a la red interna de
la planta (`192.168.80.x`), así que las conexiones directas a SQL Server
(ERP) y Postgres (SyncBox/inventario) dejan de funcionar. Solución: un
proyecto Laravel **nuevo y separado**, `api-datos-panal`
(`C:\laragon\www\api-datos-panal` en local; se despliega en
`192.168.80.52`, XAMPP, PHP 8.0 — mismo stack/patrón que el proyecto
hermano `api-certificados-panal` que ya corre ahí: Laravel 9, Sanctum,
`"platform":{"php":"8.0.2"}` fijado en `composer.json` para que Composer
resuelva igual que en el servidor real).

- Expone dos endpoints de sólo lectura detrás de `auth:sanctum` (token fijo
  tipo API key, igual que `api-certificados-panal`):
  `GET /api/erp/pedidos-pl` y `GET /api/inventario/existencias` — corren
  exactamente las mismas consultas fijas que ya usa esta app
  (`App\Support\ConsultasErp`/`ConsultasInventario`, copiadas ahí).
- En esta app: `config/erp.php` y `config/inventario.php` tienen un flag
  `modo` (`db` = conexión directa de siempre; `api` = vía el puente), más
  `api_url`/`api_token`. El cambio está aislado en `filas()` de
  `App\Services\Erp\ConsultaDirectaErp` y
  `App\Services\Inventario\ConsultaExistenciasSyncBox` — el resto de la
  sincronización, validación de reparto y UI no se tocó.
- Rutas públicas del lado de la planta (ya expuesto por IP pública con
  port-forward, mismo esquema que `api-certificados-panal`):
  interna `http://192.168.80.52/api-datos-panal/public`, pública
  `http://201.234.65.70/api-datos-panal/public` (o el hostname interno de
  AD `panalsrvapp`, que **sólo resuelve dentro de la red de la planta** —
  no sirve para Hostinger).

**Verificado funcionando de punta a punta por la red interna**: con
`ERP_MODO=api`/`INVENTARIO_MODO=api` apuntando a la URL interna,
`erp:sincronizar`/`inventario:sincronizar` traen los mismos datos reales
que en modo `db` (185 líneas ERP; 25 ítems + 60 filas de inventario).

**Bloqueado para Hostinger**: desde una sesión SSH real en Hostinger,
`curl` contra `http://201.234.65.70/...` da `Connection timed out` en el
*connect* (ni llega a Apache) — y pasa igual con `api-certificados-panal`
(el proyecto hermano que ya funcionaba), así que **no es un problema de
`api-datos-panal` ni de este código**: el puerto 80 de esa IP pública no
está respondiendo desde internet ahora mismo, para ningún proyecto.
Pendiente de TI/redes de la planta: confirmar si la IP pública sigue
siendo esa, si el port-forward del puerto 80 → `192.168.80.52` sigue
activo, o si el ISP está bloqueando el puerto entrante.

**Para retomar** (objetivo: lunes 2026-10-19): en cuanto redes confirme que
el puerto 80 responde desde afuera, sólo falta cambiar
`ERP_API_URL`/`INVENTARIO_API_URL` en el `.env` de Hostinger a la URL
pública correcta y correr `php artisan config:clear` (+ `config:cache` si
aplica) — no hace falta tocar código, ya está probado.

### Maestros manuales (`clientes`/`parametros_item`): CRUD en la app (2026-10-22)

Bug original (2026-10-21): en Hostinger la lista no mostraba `destino`
(quedaba en `#N/D`) y las toneladas salían vacías/null, aunque en local sí
funcionaba. Causa: `clientes` y `parametros_item` son datos **manuales**
(no vienen de SQL Server) y sólo las llenaba `erp:importar` — comando que
en Hostinger nunca se corre porque ahí sólo se usa `erp:sincronizar` (modo
`api`, vía `api-datos-panal`), que únicamente toca `lineas_pedido`.

Primer arreglo intentado (2026-10-21, **descartado**): republicar/releer
esas dos tablas a través de nuevos endpoints en el puente
`api-datos-panal` (`App\Services\Erp\MaestrosSync` +
`maestros:publicar`/`maestros:sincronizar`). Se decidió no seguir por ahí:
quedaba atado a que el puente estuviera accesible y migrado, y seguía
dependiendo de volver a correr `erp:importar` con el Excel cada vez que
cambiara un cliente o un ítem.

**Arreglo definitivo**: `clientes` y `parametros_item` pasan a ser
**gestionadas 100% desde la UI** por el usuario funcional de planeación,
sin depender de ningún import ni de la red interna:

- `GET /admin/clientes` (`admin/clientes/index`) y
  `GET /admin/parametros-item` (`admin/parametros-item/index`) — CRUD
  completo (crear/editar/eliminar), con el mismo patrón de los demás
  módulos admin (`x-ui.page`/`x-ui.card`, paginación, búsqueda). Gated por
  el permiso `admin.usuarios` (igual que el CRUD de usuarios).
- `App\Services\ImportadorErp` (`erp:importar`) dejó de leer/requerir las
  hojas `Clientes`/`Parametros` del Excel; sólo importa `PedidosVenta` a
  `lineas_pedido`. Las constantes `MAPA_CLIENTES`/`MAPA_PARAMETROS` y el
  método `cargarSimple()` se eliminaron.
- Se revirtieron y eliminaron `App\Services\Erp\MaestrosSync`, los
  comandos `maestros:publicar`/`maestros:sincronizar` y el `Schedule::`
  correspondiente en `routes/console.php` (nunca llegaron a desplegarse).
  El puente `api-datos-panal` sigue sirviendo `lineas_pedido` (`ERP_MODO=api`)
  e inventario (`INVENTARIO_MODO=api`) sin cambios — sólo dejó de usarse
  para estas dos tablas.
- Efecto práctico: ya no hace falta correr `erp:importar` nunca más (ni en
  local ni en Hostinger) sólo para mantener al día clientes/ítems; cada
  entorno (local y Hostinger, bases de datos separadas) mantiene sus
  propios maestros vía el CRUD. El dato inicial (15 clientes / 244
  parámetros ya cargados en local desde el Excel) **no se migra solo a
  Hostinger** — si Hostinger necesita el mismo set inicial, se captura una
  vez a mano desde `/admin/clientes` y `/admin/parametros-item`.

## Orden de implementación (referencia histórica)

1. `002-admin-roles-permisos` (seeder + login) — la feature 001 depende de sus permisos.
2. `001-seguimiento-pedidos-liberar` (importación → lista → detalle/toneladas → captura →
   semáforo → correo).
