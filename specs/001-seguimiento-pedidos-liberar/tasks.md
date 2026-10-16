---
description: "Task list — Seguimiento de Pedidos por Liberar"
---

# Tasks: Seguimiento de Pedidos por Liberar

**Input**: `specs/001-seguimiento-pedidos-liberar/` (`spec.md`, `plan.md`, `data-model.md`, `contracts/rutas.md`)

**Tests**: incluidos (Principio IV de la constitución — reglas de negocio con pruebas primero).

**Organización**: por historia de usuario. US1, US2, US3 y US6 forman el MVP (P1); US4 y US5 son P2.

## Formato: `[ID] [P?] [Story] Descripción`

- **[P]**: puede ir en paralelo (archivos distintos, sin dependencias).

---

## Phase 1: Setup (infraestructura compartida)

- [ ] T001 Configurar `.env` para MySQL (`DB_CONNECTION=mysql`, `DB_DATABASE=seguimiento_pedidos`), `APP_NAME`, `APP_LOCALE=es`, `APP_TIMEZONE=America/Bogota`; crear la base `seguimiento_pedidos`.
- [ ] T002 `composer require maatwebsite/excel`; publicar config (`config/excel.php`).
- [ ] T003 [P] Configurar `phpunit.xml` para pruebas con SQLite en memoria; verificar `php artisan test` base en verde.
- [ ] T004 [P] Crear `app/Support/EstadosPedido.php` con `POR_LIBERAR = ['PL','PP']` y `app/Enums/EstadoSeguimiento.php`.
- [ ] T005 [P] Crear layout `resources/views/layouts/app.blade.php` (Tailwind) con navegación mínima.

---

## Phase 2: Foundational (bloqueante para todas las historias)

**⚠️ Ninguna historia puede empezar hasta terminar esta fase.**

- [ ] T006 Migración `create_lineas_pedido_table` (columnas y índices de `data-model.md`; único `(pedido,item)`, índices `estado`, `nit_cliente`, `activo`).
- [ ] T007 [P] Migración `create_clientes_table` (único `nit_cliente`).
- [ ] T008 [P] Migración `create_parametros_item_table` (único `item`, `factor_kg` DECIMAL(14,6)).
- [ ] T009 [P] Migración `create_seguimientos_table` (único `(pedido,item)`, `estado_seguimiento` enum, FK `actualizado_por`→`users`).
- [ ] T010 [P] Modelo `LineaPedido` (`$casts` de fechas/decimales; `item` y `nit_cliente` string; scope `activas()`).
- [ ] T011 [P] Modelo `Cliente`.
- [ ] T012 [P] Modelo `ParametroItem`.
- [ ] T013 [P] Modelo `Seguimiento` con accessors `getAlertaAttribute()` y `getComentarioCorreoAttribute()`, cast de `fecha_tentativa_cargue`, relación `actualizadoPor()`.
- [ ] T014 Ejecutar `php artisan migrate` y confirmar el esquema.

---

## Phase 3: User Story 6 — Importar datos del ERP desde Excel (Priority: P1) 🎯 MVP (bloqueante de datos)

**Objetivo**: `php artisan erp:importar <ruta>` carga las 3 hojas de forma idempotente.

**Prueba independiente**: correr el comando sobre `tests/fixtures/erp-demo.xlsx` y verificar conteos; segunda corrida no cambia nada; `seguimientos` intactos.

### Tests (primero)

- [ ] T015 [P] [US6] Script `tests/fixtures/build-erp-demo.php` que genera `tests/fixtures/erp-demo.xlsx` (3 hojas, ~8 líneas: 1 ítem `7x`, 1 ítem sin parámetro, 1 NIT sin cliente, estados `PL`/`PP`/`CO`). Versionar el `.xlsx`.
- [ ] T016 [US6] `tests/Feature/ImportarErpExcelTest.php`: conteos por hoja; idempotencia (2ª corrida); `seguimientos` preexistentes intactos; hoja con encabezado inválido → excepción + rollback (sin filas).

### Implementación

- [ ] T017 [P] [US6] `app/Imports/LineasPedidoImport.php`, `ClientesImport.php`, `ParametrosImport.php` (mapeo encabezado→columna; `item`/`nit_cliente` como string sin recorte de ceros).
- [ ] T018 [US6] `app/Services/ImportadorErp.php`: valida hojas/encabezados; `DB::transaction`; `upsert` por clave natural; marca `activo=false` líneas ausentes y `activo=true` + `importado_en=now()` presentes; no toca `seguimientos`; devuelve resumen por hoja.
- [ ] T019 [US6] `app/Console/Commands/ImportarErpExcel.php` (`erp:importar {ruta}`) que llama a `ImportadorErp` y renderiza la tabla resumen; exit code 1 en error de validación.
- [ ] T020 [US6] `app/Http/Controllers/ImportacionController.php@store` (permiso `erp.importar`): valida `archivo`, lo guarda en `storage/app/importaciones/`, llama a `ImportadorErp`, redirige con resumen.

**Checkpoint**: hay datos en MySQL para el resto de historias.

---

## Phase 4: User Story 1 — Consultar pedidos por liberar con totales y destino (Priority: P1) 🎯 MVP

**Objetivo**: lista de pedidos `PL`/`PP` con destino, fecha de solicitud, cantidad total y toneladas totales.

**Prueba independiente**: sembrar líneas mixtas y verificar filtrado + agregados en `/pedidos-liberar`.

### Tests (primero)

- [ ] T021 [P] [US1] `tests/Feature/PedidosPorLiberarQueryTest.php`: sólo `PL`/`PP`; excluye `item` `7%`; sólo `activo`; `destino` por NIT (`B66061078→ESPAÑA`) y `#N/D` sin cliente; `cantidad_total = Σ`; `fecha_solicitud = MIN(fecha_elaboracion)`; `estado_revision` según exista `seguimientos`.

### Implementación

- [ ] T022 [US1] `app/Services/PedidosPorLiberarQuery.php`: `baseQuery()` (whereIn estado, `item not like '7%'`, `activo`, leftJoin `clientes`, leftJoin `parametros_item`, `selectRaw` con `toneladas_linea` y `sin_factor`).
- [ ] T023 [US1] `PedidosPorLiberarQuery::resumenPorPedido($filtros)`: group by `pedido` con `SUM`/`MIN`/`MAX`; `toneladas_incompleto`; `estado_revision` vía `EXISTS`; filtros de FR-020 (destino, estado, revisión, `q`); paginación 50.
- [ ] T024 [US1] `PedidosPorLiberarController@index` (permiso `pedidos.ver`) + ruta `GET /pedidos-liberar`.
- [ ] T025 [US1] Vista `resources/views/pedidos-liberar/index.blade.php`: tabla paginada, filtros (destino, estado, revisión), buscador, badges (`PL`/`PP`, `REVISADO`/`POR REVISAR`, "toneladas incompletas").
- [ ] T026 [US1] `tests/Feature/AutorizacionPedidosTest.php` (parte index): `403` sin `pedidos.ver`.

**Checkpoint**: la lista principal funciona y reemplaza la consulta Power Query.

---

## Phase 5: User Story 2 — Detalle del pedido y toneladas por ítem (Priority: P1) 🎯 MVP

**Objetivo**: vista de detalle con líneas, toneladas por línea y totales del pedido.

### Tests (primero)

- [ ] T027 [P] [US2] `tests/Unit/ToneladasTest.php`: `2400 × 0.549125 / 1000 = 1.31790`; suma de líneas; ítem sin factor → `null` + pedido "incompleto".

### Implementación

- [ ] T028 [US2] `PedidosPorLiberarQuery::detallePedido($pedido)`: `baseQuery()->where('pedido',...)` + leftJoin `seguimientos`; devuelve líneas + totales del pedido; `404` si vacío.
- [ ] T029 [US2] `PedidosPorLiberarController@show` (permiso `pedidos.ver`) + ruta `GET /pedidos-liberar/{pedido}`.
- [ ] T030 [US2] Vista `resources/views/pedidos-liberar/show.blade.php`: cabecera con totales (cantidad total, toneladas totales + marca incompleto, fecha de solicitud, cliente, destino) y tabla de líneas (item, descripción, grupo, UM, cantidad pedida, cantidad despachada, fecha requerida, toneladas de línea, "sin factor").

**Checkpoint**: se ve el detalle y las toneladas — dato nuevo pedido explícitamente.

---

## Phase 6: User Story 3 — Capturar estado de materiales, observaciones y fecha tentativa (Priority: P1) 🎯 MVP

**Objetivo**: registrar/actualizar por línea los 3 campos + `estado_seguimiento`, con trazabilidad.

### Tests (primero)

- [ ] T031 [P] [US3] `tests/Feature/GuardarSeguimientoTest.php`: crea seguimiento por `(pedido,item)`; `actualizado_por = auth()->id()`; recarga persiste; 2º guardado actualiza (no duplica); pedido pasa a `REVISADO`.
- [ ] T032 [P] [US3] `tests/Feature/AutorizacionPedidosTest.php` (parte seguimiento): sin `seguimiento.editar` → campos read-only y `PUT` → `403`.

### Implementación

- [ ] T033 [US3] `app/Http/Requests/GuardarSeguimientoRequest.php` (reglas de `data-model.md`; valida que cada `item` pertenezca al `{pedido}`, esté `activo` y en `PL`/`PP`).
- [ ] T034 [US3] `PedidosPorLiberarController@guardarSeguimiento` (permiso `seguimiento.editar`) + ruta `PUT /pedidos-liberar/{pedido}/seguimiento`: `upsert` en `seguimientos`, setea `actualizado_por` y copia `descripcion`/`cantidad` de la línea vigente.
- [ ] T035 [US3] Añadir a `show.blade.php` el mini-formulario por línea (`estado_materiales`, `observaciones_plan`, `fecha_tentativa_cargue`, `estado_seguimiento`), deshabilitado sin `seguimiento.editar`; mostrar `estado_revision` del pedido.

**Checkpoint**: MVP completo — reemplaza `RegistrarPedido` / `CargarHistorial` / `BD_Seguimiento`.

---

## Phase 7: User Story 4 — Panel de seguimiento con semáforo (Priority: P2)

### Tests (primero)

- [ ] T036 [P] [US4] `tests/Unit/SeguimientoDerivadosTest.php`: `alerta` para cada `estado_seguimiento`; `comentario_correo` con y sin `fecha_tentativa_cargue` (`26/08/2026`).

### Implementación

- [ ] T037 [US4] Mostrar en `show.blade.php` (y opcionalmente en `index`) la `alerta` (🔴/🟡/✅) y el `comentario_correo` por línea; resumen de alertas del pedido.
- [ ] T038 [US4] Filtro por `estado_seguimiento` / alerta en `index` (extiende `resumenPorPedido`).

**Checkpoint**: capa de estado/alerta equivalente a las columnas `ESTADO`/`ALERTA`/`COMENTARIO CORREO`.

---

## Phase 8: User Story 5 — Generar el correo de cargues (Priority: P2)

### Tests (primero)

- [ ] T039 [P] [US5] `tests/Feature/CorreoCarguesTest.php`: incluye sólo líneas con `fecha_tentativa_cargue`; agrupadas por pedido; frase `"Pedido disponible para cargue sobre el dd/mm/aaaa"`; `403` sin `correo.generar`.

### Implementación

- [ ] T040 [US5] `app/Services/CorreoCarguesBuilder.php`: `html()` y `texto()` desde las líneas `PL`/`PP` sin `7x` con `fecha_tentativa_cargue`, agrupadas por pedido; usa `Seguimiento::comentarioCorreo`.
- [ ] T041 [P] [US5] `app/Mail/CorreoCargues.php` + vista `resources/views/mail/cargues.blade.php` (render del `html()`).
- [ ] T042 [US5] `CorreoCarguesController@preview` (permiso `correo.generar`) + ruta `GET /cargues/correo` + vista `resources/views/cargues/correo.blade.php` (HTML + `<pre>` de texto + botón copiar).

**Checkpoint**: reemplaza `PrepararCorreo`.

---

## Phase 9: Polish & Cross-Cutting

- [ ] T043 [P] Añadir enlaces de navegación (lista de pedidos, correo de cargues, importación) al layout, condicionados por permiso (`@can`).
- [ ] T044 [P] Mensajes flash de éxito/error consistentes; formato de fechas `d/m/Y` y números con separador de miles en todas las vistas.
- [ ] T045 Ejecutar `vendor/bin/pint`; dejar `pint --test` sin cambios.
- [ ] T046 Ejecutar `php artisan test` completo en verde.
- [ ] T047 Validar `quickstart.md` de punta a punta con el archivo real `Consulta SQL Pedidos-Despachos.xlsx`.
- [ ] T048 [P] Actualizar `CLAUDE.md` con las tablas/servicios/rutas nuevos.

---

## Dependencias y orden de ejecución

- **Phase 1 → Phase 2**: setup antes que migraciones/modelos.
- **Phase 2 → todas las historias**.
- **US6 (Phase 3)** provee datos: conviene primero, aunque US1/US2 pueden probarse con factories.
- **US1 (Phase 4)** antes que **US2 (Phase 5)** (comparten `PedidosPorLiberarQuery`).
- **US3 (Phase 6)** depende de la vista `show` de US2.
- **US4 (Phase 7)** y **US5 (Phase 8)** dependen de US3 (datos de seguimiento). Pueden ir en paralelo entre sí.
- **Phase 9** al final.

### Paralelizables

- T003–T005; T006–T013 (migraciones/modelos en archivos distintos); T017 (los 3 imports);
  tests marcados `[P]` de cada historia; T040/T041.

## Estrategia de MVP

1. Phase 1 + Phase 2.
2. Phase 3 (US6) → hay datos.
3. Phase 4 (US1) → **demo: lista con totales**.
4. Phase 5 (US2) → **demo: detalle + toneladas**.
5. Phase 6 (US3) → **MVP: captura de seguimiento**. Parar y validar.
6. Phase 7–8 (US4, US5) como incrementos.
7. Phase 9 pulido.
