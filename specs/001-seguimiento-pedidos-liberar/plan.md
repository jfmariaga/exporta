# Implementation Plan: Seguimiento de Pedidos por Liberar

**Branch**: `001-seguimiento-pedidos-liberar` | **Date**: 2026-08-28 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-seguimiento-pedidos-liberar/spec.md`

## Summary

Reemplazar el MVP en Excel por un módulo Laravel que: (1) importa desde Excel las líneas de
pedido, clientes y parámetros del ERP a tablas MySQL propias; (2) consulta las líneas en estado
`PL`/`PP` excluyendo ítems `7x`, cruza destino por NIT y calcula toneladas por línea
(`cantidad_pedida × factor_kg / 1000`); (3) agrega por pedido cantidad total, toneladas totales
y fecha de solicitud (`MIN(fecha_elaboracion)`); (4) permite capturar por línea el estado de
materiales, las observaciones de plan y la fecha tentativa de cargue, con panel semáforo y
derivados `alerta` / `comentario_correo`; (5) genera el cuerpo del correo de cargues. Toda la
lógica de negocio (fórmulas, filtros, derivados) se cubre con pruebas antes de la UI.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12.68

**Primary Dependencies**: `maatwebsite/excel` (importación de Excel), `spatie/laravel-permission`
(vía feature 002), Blade + Tailwind CSS v4 + Vite 7

**Storage**: MySQL 8 (`DB_CONNECTION=mysql`). Tablas espejo del ERP + tabla propia de
seguimiento. SQLite en memoria sólo para pruebas.

**Testing**: PHPUnit 11 (`php artisan test`). Unit para fórmulas/derivados; Feature para
consultas, importación, autorización y vistas. `laravel/pint` para estilo.

**Target Platform**: Servidor web Linux/Windows (Laragon en desarrollo). App server-rendered.

**Project Type**: Web application (monolito Laravel, sin frontend separado).

**Performance Goals**: Lista de pedidos < 3 s con ~2.000 líneas / cientos de pedidos.
Consultas agregadas resueltas en SQL, no en PHP.

**Constraints**: El ERP es de sólo lectura (Principio II). Importación idempotente y
transaccional (Principio III). Autorización por permiso (Principio V). Dominio en español
(Principio VI). Trazabilidad de cambios de seguimiento (Principio VII).

**Scale/Scope**: 5 tablas nuevas, 2 servicios de dominio, 1 comando artisan, 3 controladores,
~5 vistas Blade, 1 Mailable. ~15–20 pruebas.

## Constitution Check

*GATE: debe pasar antes de implementar. Re-verificar tras el diseño detallado.*

| Principio | Cumplimiento en este plan |
|---|---|
| I. Stack fijo (Blade) | Sólo Blade + Tailwind; sin JS framework. ✅ |
| II. ERP sólo lectura | Datos entran por `erp:importar`; tablas espejo; nada se escribe al ERP. ✅ |
| III. Importación idempotente | Upsert por `(pedido,item)` / `nit_cliente` / `item`, en transacción. ✅ |
| IV. Reglas con pruebas primero | `ToneladasTest`, `PedidosPorLiberarQueryTest`, `SeguimientoDerivadosTest` antes de vistas. ✅ |
| V. Autorización por permiso | Middleware `can:pedidos.ver` / `can:seguimiento.editar` / `can:correo.generar` / `can:erp.importar`. ✅ |
| VI. Dominio en español | Tablas/columnas/rutas/enums en español. ✅ |
| VII. Trazabilidad | `seguimientos.actualizado_por` + timestamps en cada guardado. ✅ |

**Sin violaciones.** Complexity Tracking no aplica.

## Project Structure

### Documentation (this feature)

```text
specs/001-seguimiento-pedidos-liberar/
├── spec.md
├── plan.md              # Este archivo
├── data-model.md        # Esquema de tablas y modelos
├── quickstart.md        # Puesta en marcha y verificación manual
├── contracts/
│   └── rutas.md         # Contrato de rutas HTTP (métodos, permisos, payloads)
└── tasks.md             # Generado por /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Console/Commands/
│   └── ImportarErpExcel.php          # php artisan erp:importar {ruta}
├── Imports/                          # maatwebsite/excel
│   ├── LineasPedidoImport.php
│   ├── ClientesImport.php
│   └── ParametrosImport.php
├── Models/
│   ├── LineaPedido.php               # tabla: lineas_pedido
│   ├── Cliente.php                   # tabla: clientes
│   ├── ParametroItem.php             # tabla: parametros_item
│   └── Seguimiento.php               # tabla: seguimientos  (+ accessors alerta, comentarioCorreo)
├── Services/
│   ├── PedidosPorLiberarQuery.php    # query base + resumenPorPedido() + detallePedido()
│   └── CorreoCarguesBuilder.php      # cuerpo HTML/texto del correo de cargues
├── Http/Controllers/
│   ├── PedidosPorLiberarController.php  # index, show, guardarSeguimiento
│   ├── CorreoCarguesController.php      # preview
│   └── ImportacionController.php        # store (subida de Excel opcional por UI)
├── Http/Requests/
│   └── GuardarSeguimientoRequest.php
└── Mail/
    └── CorreoCargues.php             # Mailable (render del cuerpo)

database/migrations/
├── ####_create_lineas_pedido_table.php
├── ####_create_clientes_table.php
├── ####_create_parametros_item_table.php
└── ####_create_seguimientos_table.php

resources/views/
├── layouts/app.blade.php
├── pedidos-liberar/index.blade.php
├── pedidos-liberar/show.blade.php
├── cargues/correo.blade.php
└── mail/cargues.blade.php

routes/web.php                        # rutas del módulo (auth + can:)

tests/
├── Unit/
│   ├── ToneladasTest.php
│   └── SeguimientoDerivadosTest.php
└── Feature/
    ├── PedidosPorLiberarQueryTest.php
    ├── ImportarErpExcelTest.php
    ├── GuardarSeguimientoTest.php
    ├── CorreoCarguesTest.php
    └── AutorizacionPedidosTest.php
```

**Structure Decision**: Monolito Laravel estándar (Opción "Web application" del template sin
separar frontend). La lógica de dominio vive en `app/Services` para ser testeable sin HTTP;
los controladores son delgados.

## Diseño técnico

### Configuración

- `.env`: `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`,
  `DB_DATABASE=seguimiento_pedidos`, `DB_USERNAME=root`, `DB_PASSWORD=` (Laragon por defecto).
- `APP_NAME="Seguimiento de Pedidos"`, `APP_LOCALE=es`, `APP_TIMEZONE=America/Bogota`.
- `composer require maatwebsite/excel`.
- `phpunit.xml`: conexión de pruebas SQLite en memoria.

### Tablas (ver `data-model.md` para el detalle de columnas)

| Tabla | Origen | Clave natural | Notas |
|---|---|---|---|
| `lineas_pedido` | hoja `PedidosVenta` / `Pedidos Liberar` | `(pedido, item)` | `item`, `nit_cliente` como `string`. Índices en `estado`, `nit_cliente`. Columna `activo` (bool) para marcar líneas que ya no vienen del ERP. `importado_en` timestamp. |
| `clientes` | hoja `Clientes` | `nit_cliente` | `destino` = país. |
| `parametros_item` | hoja `Parametros` | `item` | `factor_kg` DECIMAL(14,6). |
| `seguimientos` | app | `(pedido, item)` | `estado_seguimiento` ENUM. `actualizado_por` FK→`users`. `timestamps`. |

### Servicio `PedidosPorLiberarQuery`

- `baseQuery()`:
  ```
  DB::table('lineas_pedido as l')
    ->where('l.estado', ... )                       // whereIn('l.estado', ['PL','PP'])
    ->where('l.item', 'not like', '7%')
    ->where('l.activo', true)
    ->leftJoin('clientes as c', 'c.nit_cliente', '=', 'l.nit_cliente')
    ->leftJoin('parametros_item as p', 'p.item', '=', 'l.item')
    ->selectRaw('l.*, c.cliente, COALESCE(c.destino, "#N/D") as destino,
                 p.factor_kg,
                 (l.cantidad_pedida * p.factor_kg / 1000) as toneladas_linea,
                 (p.item is null) as sin_factor')
  ```
  Los valores `['PL','PP']` se leen de una constante `App\Support\EstadosPedido::POR_LIBERAR`
  para cumplir FR-003 de forma centralizada.
- `resumenPorPedido($filtros)`: agrupa por `l.pedido`:
  `SUM(cantidad_pedida) as cantidad_total`,
  `SUM(cantidad_pedida * factor_kg) / 1000 as toneladas_total`,
  `MAX(p.item is null) as toneladas_incompleto`,
  `MIN(fecha_elaboracion) as fecha_solicitud`,
  `MAX(nit_cliente)`, `MAX(cliente)`, `MAX(destino)`, `MAX(estado)`.
  Estado de revisión: `EXISTS (select 1 from seguimientos s where s.pedido = l.pedido)` →
  `REVISADO` / `POR REVISAR`. Aplica filtros de FR-020 (destino, estado, revisión, búsqueda).
  Pagina de a 50.
- `detallePedido($pedido)`: `baseQuery()->where('l.pedido',$pedido)` + `leftJoin seguimientos`
  para traer los 3 campos + `estado_seguimiento`. Devuelve también los totales del pedido.

> **Redondeo**: las toneladas se calculan en SQL con la precisión nativa y se **presentan**
> redondeadas a 5 decimales en la vista y en los assertions de prueba. El almacenamiento del
> cálculo no persiste (se recalcula siempre desde `cantidad_pedida` y `factor_kg`).

### Modelo `Seguimiento` — derivados

- `getAlertaAttribute()`: `match($this->estado_seguimiento) { 'Pendiente' => '🔴',
  'En validación' => '🟡', 'Listo para cargar' => '✅', default => '' }`.
- `getComentarioCorreoAttribute()`: `$this->fecha_tentativa_cargue
  ? 'Pedido disponible para cargue sobre el '.$this->fecha_tentativa_cargue->format('d/m/Y')
  : 'PENDIENTE POR DEFINIR FECHA'`.
- Enum PHP `App\Enums\EstadoSeguimiento` (`Pendiente`, `EnValidacion`, `ListoParaCargar`) con
  etiqueta legible; se guarda el valor de texto legible para casar con el MVP.

### Comando `erp:importar {ruta}`

- Valida existencia del archivo y de las 3 hojas con encabezados esperados (mapa
  encabezado→columna). Nombres de hoja aceptados: `PedidosVenta` o `Pedidos Liberar` para las
  líneas; `Clientes`; `Parametros` (o `Parámetros`).
- `DB::transaction`: por cada hoja hace `upsert` (Eloquent `upsert()` o `Model::upsert`) por
  clave natural. Para `lineas_pedido`: marca `activo=false` las líneas cuyo `(pedido,item)` no
  vino en el archivo (o sólo dentro del universo importado), y `activo=true` + `importado_en=now()`
  las presentes. `item` y `nit_cliente` se normalizan a `string` (sin recorte de ceros).
- No toca las tablas de seguimiento (FR-002 / FR-019).
- Salida: tabla resumen `hoja | insertadas | actualizadas | desactivadas`.
- Disparable también desde el componente Volt `pedidos/index` (botón "Importar ERP",
  permiso `erp.importar`).

### Fuente de datos del ERP (Excel vs. directo) — ver `erp-fuentes.md`

`config/erp.php` → `ERP_FUENTE` selecciona cómo se llena `lineas_pedido`:

- **`excel`** (default): `erp:importar <archivo>` (arriba).
- **`directo`**: `erp:sincronizar` → `App\Services\Erp\ConsultaDirectaErp` corre
  `App\Support\ConsultasErp::pedidosPl()` (la consulta "Pedidos PL" del Power Query original)
  sobre la conexión de **sólo lectura** `erp` (`driver sqlsrv`, `panalsrvdb`/`panalsas`),
  mapea los alias del ERP a las columnas de la tabla y delega en `LineasPedidoSync`.
  `clientes` y `parametros_item` siguen viniendo del Excel.

`App\Services\Erp\LineasPedidoSync` es el **único** escritor de `lineas_pedido` (transacción,
"todas inactivas → upsert `(pedido,item)` → recuento", aborta si el origen da 0 filas) y
comparte los normalizadores (`codigoItem`, `textoNit`, `aNumero`, `aFecha`).
Requisitos de `directo`: extensión PHP `pdo_sqlsrv` + `.env` `ERP_*`. Opcional:
`Schedule::command('erp:sincronizar')` en `routes/console.php` (ya presente, sólo si `directo`).

### `CorreoCarguesBuilder`

- Toma las líneas con `seguimientos.fecha_tentativa_cargue IS NOT NULL` (universo `PL`/`PP`,
  sin ítems `7x`), agrupa por pedido, y arma:
  - `html()`: tabla por pedido (Pedido, Cliente, Ítem, Descripción, Cantidad, Frase de cargue).
  - `texto()`: versión monoespaciada equivalente.
- La frase por línea es el `comentario_correo` del `Seguimiento`.
- `CorreoCargues` (Mailable) sólo renderiza `mail/cargues.blade.php` con el resultado del
  builder; el envío queda fuera de alcance (se muestra en pantalla para copiar).

### Rutas (`routes/web.php`) — ver `contracts/rutas.md`

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/pedidos-liberar', [PedidosPorLiberarController::class, 'index'])
        ->middleware('can:pedidos.ver')->name('pedidos.index');
    Route::get('/pedidos-liberar/{pedido}', [PedidosPorLiberarController::class, 'show'])
        ->middleware('can:pedidos.ver')->name('pedidos.show');
    Route::put('/pedidos-liberar/{pedido}/seguimiento', [PedidosPorLiberarController::class, 'guardarSeguimiento'])
        ->middleware('can:seguimiento.editar')->name('pedidos.seguimiento');
    Route::get('/cargues/correo', [CorreoCarguesController::class, 'preview'])
        ->middleware('can:correo.generar')->name('cargues.correo');
    Route::post('/importaciones', [ImportacionController::class, 'store'])
        ->middleware('can:erp.importar')->name('importaciones.store');
});
```

### Vistas

- `pedidos-liberar/index`: tabla paginada + filtros (destino, estado, revisión) + buscador.
  Badges: estado `PL`/`PP`, revisión `REVISADO`/`POR REVISAR`, `toneladas_incompleto`.
- `pedidos-liberar/show`: cabecera con totales (cantidad total, toneladas totales +
  marca incompleto, fecha de solicitud, cliente, destino). Tabla de líneas; por línea un
  mini-formulario (`estado_materiales`, `observaciones_plan`, `fecha_tentativa_cargue`,
  `estado_seguimiento`) que hace `PUT` (un submit por línea, o uno por pedido con todas las
  líneas). Muestra `alerta` y `comentario_correo`. Campos deshabilitados sin
  `seguimiento.editar`.
- `cargues/correo`: render del `html()` + bloque `<pre>` con `texto()` y botón "copiar".

### Pruebas (Principio IV — primero)

| Prueba | Qué fija |
|---|---|
| `Unit/ToneladasTest` | `2400 × 0.549125 / 1000 = 1.31790`; suma de líneas; ítem sin factor → null + incompleto. |
| `Unit/SeguimientoDerivadosTest` | `alerta` por cada estado; `comentario_correo` con y sin fecha (`26/08/2026`). |
| `Feature/PedidosPorLiberarQueryTest` | Sólo `PL`/`PP`; excluye `item` `7%`; destino por NIT (`B66061078→ESPAÑA`); `#N/D` sin cliente; `cantidad_total`; `fecha_solicitud = MIN`; estado de revisión. |
| `Feature/ImportarErpExcelTest` | Conteos por hoja con un `.xlsx` fixture; idempotencia (2ª corrida no cambia conteos); `seguimientos` intactos; hoja inválida → error + rollback. |
| `Feature/GuardarSeguimientoTest` | Upsert por `(pedido,item)`; `actualizado_por` = usuario; recarga persiste; 2º guardado actualiza. |
| `Feature/CorreoCarguesTest` | Incluye sólo líneas con fecha; frase correcta; agrupado por pedido. |
| `Feature/AutorizacionPedidosTest` | `403` sin `pedidos.ver` / `seguimiento.editar` / `correo.generar` / `erp.importar`; campos read-only sin `seguimiento.editar`. |

### Fixture de pruebas

`tests/fixtures/erp-demo.xlsx` con 3 hojas y ~8 líneas (incluye: 1 ítem `7x`, 1 ítem sin
parámetro, 1 NIT sin cliente, 2 pedidos, estados `PL`/`PP`/`CO`). Se genera una vez con un
script y se versiona.

## Complexity Tracking

> Sin violaciones a la constitución. No aplica.
