# Feature Specification: Seguimiento de Pedidos por Liberar

**Feature Branch**: `001-seguimiento-pedidos-liberar`

**Created**: 2026-08-28

**Status**: Implementado

> **Enmienda 2026-08-28.** Cambios sobre la versión original, ya reflejados en el código:
> - UI en **Livewire 3 (Volt) sobre Breeze**; tablas con **paginación**.
> - La **fecha tentativa de cargue**, el **estado de seguimiento** y la **alerta** se registran
>   a **nivel de pedido** (tabla `seguimiento_pedidos`), no por ítem.
> - Nuevas columnas de línea (mismas reglas que "estado materiales"/"observaciones plan"):
>   **`inventario`** (capturada) y **`porcentaje_cumplimiento`** (derivada = `inventario / cantidad_pedida × 100`).
>   A nivel de pedido: `Σ inventario / Σ cantidad_pedida × 100`.
> - Nuevo filtro **por parámetro** (`parametros_item.categoria`) en el **detalle del pedido**
>   (filtra las líneas de ese pedido), no en el listado general.

**Input**: Reemplazo web del MVP en Excel `Pedidos-Despachos de Venta EXP.xlsm`. Consultar los
pedidos de venta de exportación en estado **PL/PP** ("por liberar"), traer su detalle por ítem,
cruzarlo con el **destino** (país) a través del NIT del cliente, calcular **toneladas** con el
factor de kilogramos por ítem, calcular **cantidad total** y **fecha de solicitud** del pedido,
ignorar los ítems que empiezan por `7`, y permitir que planeación capture por ítem el
**estado de materiales**, las **observaciones de plan** y la **fecha tentativa de cargue**, con
un panel de seguimiento tipo semáforo y la generación del cuerpo del correo de cargues.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consultar pedidos por liberar con totales y destino (Priority: P1)

Como planeador de exportaciones, quiero ver la lista de pedidos de venta que están **por liberar**
(estado `PL` o `PP`), cada uno con su cliente, país de **destino**, **fecha de solicitud**,
**cantidad total** y **toneladas totales**, para saber qué pedidos requieren gestión y su tamaño.

**Why this priority**: Es el corazón del reemplazo del Excel. Sin esta vista no hay producto;
con sólo esta historia ya se entrega valor (deja de depender de la consulta Power Query manual).

**Independent Test**: Cargar datos de ejemplo con líneas en varios estados e ítems, abrir
`/pedidos-liberar` y verificar que sólo aparecen pedidos con líneas `PL`/`PP` sin ítems `7x`,
con los agregados correctos por pedido.

**Acceptance Scenarios**:

1. **Given** líneas de pedido en estados `PL`, `PP`, `CO`, `CE`, **When** abro la lista,
   **Then** sólo veo pedidos que tienen al menos una línea `PL` o `PP`.
2. **Given** un pedido con ítems `001131` (cant. 2400) y `700010` (cant. 5000), **When** veo el
   pedido, **Then** el ítem `700010` se excluye del detalle y de todos los agregados.
3. **Given** un pedido con líneas de fechas de elaboración `2026-08-04` y `2026-08-06`,
   **When** veo el pedido, **Then** la **fecha de solicitud** mostrada es `2026-08-04`.
4. **Given** un pedido del cliente NIT `B66061078`, **When** veo el pedido, **Then** el
   **destino** mostrado es `ESPAÑA` (tomado de la hoja Clientes por NIT).
5. **Given** un pedido con líneas que suman 47.550 unidades pedidas, **When** veo el pedido,
   **Then** la **cantidad total** mostrada es `47.550`.

---

### User Story 2 - Ver el detalle del pedido y calcular toneladas por ítem (Priority: P1)

Como planeador, quiero abrir un pedido y ver sus líneas por ítem con la **cantidad pedida** y las
**toneladas** de cada línea (y el total del pedido), para dimensionar el cargue.

**Why this priority**: Las toneladas son un dato de negocio central que hoy no existe en el
Excel y que se pidió explícitamente. Va junto con US1 en el MVP.

**Independent Test**: Abrir `/pedidos-liberar/{pedido}` de un pedido con ítems de factor
conocido y verificar el cálculo `cantidad_pedida × factor_kg / 1000` por línea y su suma.

**Acceptance Scenarios**:

1. **Given** el ítem `001131` con `factor_kg = 0.549125` y `cantidad_pedida = 2400`,
   **When** veo la línea, **Then** las toneladas de la línea son `1.31790` (redondeo a 5 dec.).
2. **Given** un pedido con líneas de `1.31790` t y `2.10000` t, **When** veo el pedido,
   **Then** las **toneladas totales** son `3.41790`.
3. **Given** un ítem sin registro en Parametros, **When** veo la línea, **Then** las toneladas
   se muestran vacías con la marca **"sin factor"** y el total del pedido se marca **"incompleto"**.
4. **Given** un pedido, **When** veo el detalle, **Then** cada línea muestra: item, descripción,
   grupo, unidad de medida, cantidad pedida, cantidad despachada, fecha requerida y toneladas.

---

### User Story 3 - Capturar estado de materiales, observaciones de plan y fecha tentativa de cargue (Priority: P1)

Como planeador, quiero registrar por ítem del pedido el **ESTADO DE MATERIALES**, las
**OBSERVACIONES PLAN** y la **FECHA TENTATIVA DE CARGUE**, y que queden guardados y asociados a
mí y a la fecha de captura, para dar seguimiento a la liberación.

**Why this priority**: Es la razón de ser del MVP (las macros `RegistrarPedido` /
`CargarHistorial`). Reemplaza la hoja `BD_Seguimiento`.

**Independent Test**: En el detalle de un pedido, llenar los tres campos de una línea, guardar,
recargar y verificar persistencia + usuario + marca de tiempo; volver a guardar reemplaza los
valores previos de esa línea.

**Acceptance Scenarios**:

1. **Given** una línea sin seguimiento, **When** ingreso `ESTADO MATERIALES = "OK"`,
   `OBSERVACIONES PLAN = "Por programar"`, `FECHA TENTATIVA = 2026-08-26` y guardo,
   **Then** al recargar los valores persisten y se registra mi usuario y la fecha/hora.
2. **Given** una línea con seguimiento previo, **When** cambio la fecha tentativa y guardo,
   **Then** el registro se actualiza (no se crea uno nuevo) y refleja el nuevo `actualizado_por`.
3. **Given** un pedido con al menos una línea con seguimiento, **When** veo la lista de pedidos,
   **Then** ese pedido aparece como **"REVISADO"**; si ninguna línea tiene seguimiento, como
   **"POR REVISAR"**.
4. **Given** que no tengo el permiso `seguimiento.editar`, **When** abro el detalle,
   **Then** los tres campos se muestran de sólo lectura y el `PUT` de guardado responde `403`.

---

### User Story 4 - Panel de seguimiento con semáforo (Priority: P2)

Como planeador, quiero marcar el **ESTADO** de seguimiento de cada línea
(`Pendiente` / `En validación` / `Listo para cargar`) y ver una **ALERTA** tipo semáforo
(🔴 / 🟡 / ✅) y un **COMENTARIO CORREO** generado automáticamente, para comunicar el avance.

**Why this priority**: Aporta la capa de estado/alerta de `BD_Seguimiento` (columnas `ESTADO`,
`ALERTA`, `COMENTARIO CORREO`). Útil pero el MVP funciona sin ella.

**Independent Test**: Cambiar el `estado_seguimiento` de una línea y verificar el emoji de
alerta y el texto del comentario según haya o no fecha tentativa.

**Acceptance Scenarios**:

1. **Given** `estado_seguimiento = "Pendiente"`, **Then** la alerta es `🔴`.
2. **Given** `estado_seguimiento = "En validación"`, **Then** la alerta es `🟡`.
3. **Given** `estado_seguimiento = "Listo para cargar"`, **Then** la alerta es `✅`.
4. **Given** una línea sin `fecha_tentativa_cargue`, **Then** el comentario correo es
   `"PENDIENTE POR DEFINIR FECHA"`.
5. **Given** una línea con `fecha_tentativa_cargue = 2026-08-26`, **Then** el comentario correo
   es `"Pedido disponible para cargue sobre el 26/08/2026"`.

---

### User Story 5 - Generar el correo de cargues (Priority: P2)

Como planeador, quiero generar el **cuerpo del correo de cargues** con los pedidos/líneas que
tienen fecha tentativa de cargue, para enviarlo a logística y al cliente.

**Why this priority**: Reemplaza `PrepararCorreo`. Depende de US3/US4 pero es un extra sobre el
núcleo.

**Independent Test**: Con varias líneas, unas con fecha tentativa y otras sin ella, abrir
`/cargues/correo` y verificar que el cuerpo lista sólo las que tienen fecha, con su texto.

**Acceptance Scenarios**:

1. **Given** líneas con y sin fecha tentativa, **When** genero el correo, **Then** el cuerpo
   incluye sólo las líneas con fecha, agrupadas por pedido, cada una con pedido, cliente,
   ítem, descripción, cantidad y la frase
   `"Pedido disponible para cargue sobre el dd/mm/aaaa"`.
2. **Given** el correo generado, **When** lo veo, **Then** dispongo de una versión HTML y una
   de texto plano para copiar.
3. **Given** que no tengo el permiso `correo.generar`, **When** abro `/cargues/correo`,
   **Then** recibo `403`.

---

### User Story 6 - Importar los datos del ERP desde Excel (Priority: P1)

Como administrador de datos, quiero importar el archivo `Consulta SQL Pedidos-Despachos.xlsx`
(hojas `PedidosVenta`, `Clientes`, `Parametros`) a MySQL, para alimentar las consultas.

**Why this priority**: Sin datos no hay nada que consultar. Bloqueante para US1–US5.

**Independent Test**: Ejecutar `php artisan erp:importar <ruta.xlsx>` sobre un archivo de
prueba y verificar los conteos por hoja; re-ejecutar y verificar que no hay duplicados y que
el seguimiento previo se conserva.

**Acceptance Scenarios**:

1. **Given** el archivo con 3 hojas, **When** ejecuto el comando, **Then** se cargan las
   líneas de pedido, ~15 clientes y ~244 parámetros, y el comando reporta filas por hoja.
2. **Given** una importación previa, **When** vuelvo a importar el mismo archivo,
   **Then** el número de filas por tabla no cambia (upsert por clave natural).
3. **Given** líneas de seguimiento capturadas para el pedido `179`, **When** re-importo,
   **Then** el seguimiento del pedido `179` se conserva intacto.
4. **Given** una hoja con encabezados faltantes o nombre inesperado, **When** ejecuto el
   comando, **Then** falla con un mensaje claro y no deja la base a medias (transacción).

---

### Edge Cases

- **NIT sin cliente**: la línea/pedido muestra destino `#N/D` y una marca de advertencia; no
  se excluye del listado.
- **Ítem sin factor**: toneladas vacías + marca "sin factor"; el total del pedido queda
  "incompleto".
- **Pedido que deja de estar en PL/PP tras re-importar**: desaparece del listado; su
  seguimiento se conserva en base (histórico) pero no se muestra en la vista principal.
- **Línea que desaparece del ERP** (ya no viene en el Excel): se marca como inactiva; su
  seguimiento no se borra.
- **`cantidad_pedida` = 0 o negativa**: se muestra tal cual; toneladas se calculan igual (0).
- **Fecha tentativa en el pasado**: se permite; opcionalmente se resalta en la UI.
- **Concurrencia**: si dos planeadores guardan la misma línea, gana el último y queda
  registrado su usuario/hora (sin bloqueo optimista en v1).
- **Ítems con ceros a la izquierda** (`001131`): se tratan como texto en todas las tablas y
  cruces; nunca como número.
- **NIT con guiones o prefijos alfabéticos** (`76378128-3`, `IMA031128136`): se tratan como
  texto; el cruce es por igualdad exacta de cadena.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE importar desde `Consulta SQL Pedidos-Despachos.xlsx` las hojas
  `PedidosVenta` (líneas de pedido), `Clientes` (NIT → cliente → destino) y `Parametros`
  (item → factor kg y atributos de embalaje) a tablas MySQL propias.
- **FR-002**: La importación DEBE ser idempotente (upsert por `(pedido,item)`, `nit_cliente`,
  `item`) y transaccional, y DEBE preservar los datos de seguimiento existentes.
- **FR-003**: El sistema DEBE considerar "pedidos por liberar" las líneas cuyo `estado` sea
  `PL` o `PP`.
- **FR-004**: El sistema DEBE excluir de toda consulta y agregado las líneas cuyo `item`
  empiece por el carácter `7`.
- **FR-005**: El sistema DEBE determinar el **destino** de cada pedido/línea cruzando
  `nit_cliente` con la tabla de clientes; sin coincidencia el destino es `#N/D`.
- **FR-006**: El sistema DEBE calcular las **toneladas de cada línea** como
  `cantidad_pedida × factor_kg / 1000`, con `factor_kg` tomado de Parametros por `item`.
- **FR-007**: El sistema DEBE calcular por pedido: **cantidad total** (`Σ cantidad_pedida`),
  **toneladas totales** (`Σ (cantidad_pedida × factor_kg) / 1000`) y **fecha de solicitud**
  (`MIN(fecha_elaboracion)` de sus líneas).
- **FR-008**: El sistema DEBE marcar el total de toneladas de un pedido como **incompleto**
  cuando alguna de sus líneas no tiene `factor_kg`.
- **FR-009**: El sistema DEBE mostrar una lista de pedidos por liberar (una fila por pedido)
  con: consecutivo, NIT, nombre de cliente, destino, fecha de solicitud, cantidad total,
  toneladas totales, estado (`PL`/`PP`) y estado de revisión (`REVISADO` / `POR REVISAR`).
- **FR-010**: El sistema DEBE mostrar el detalle de un pedido con sus líneas por ítem
  (item, descripción, grupo, unidad, cantidad pedida, cantidad despachada, fecha requerida,
  toneladas de la línea) y los campos de seguimiento por línea.
- **FR-011**: Los usuarios con permiso `seguimiento.editar` DEBEN poder registrar y actualizar
  por línea: `estado_materiales` (texto), `observaciones_plan` (texto), `fecha_tentativa_cargue`
  (fecha) y `estado_seguimiento` (`Pendiente` / `En validación` / `Listo para cargar`).
- **FR-012**: Cada guardado de seguimiento DEBE registrar el usuario autenticado y la marca de
  tiempo, y DEBE reemplazar (no duplicar) el registro de esa línea.
- **FR-013**: El sistema DEBE derivar la **alerta** de cada línea a partir de
  `estado_seguimiento`: `Pendiente→🔴`, `En validación→🟡`, `Listo para cargar→✅`, vacío→"".
- **FR-014**: El sistema DEBE derivar el **comentario de correo** de cada línea: sin
  `fecha_tentativa_cargue` → `"PENDIENTE POR DEFINIR FECHA"`; con fecha →
  `"Pedido disponible para cargue sobre el dd/mm/aaaa"`.
- **FR-015**: El sistema DEBE marcar un pedido como `REVISADO` si al menos una de sus líneas
  tiene registro de seguimiento; de lo contrario `POR REVISAR`.
- **FR-016**: Los usuarios con permiso `correo.generar` DEBEN poder generar el cuerpo del
  correo de cargues (HTML + texto plano) con las líneas que tienen `fecha_tentativa_cargue`,
  agrupadas por pedido.
- **FR-017**: Todas las rutas DEBEN exigir autenticación y el permiso correspondiente
  (`pedidos.ver`, `seguimiento.editar`, `correo.generar`, `erp.importar`).
- **FR-018**: El sistema DEBE tratar `item` y `nit_cliente` como cadenas de texto en
  almacenamiento y cruces (preservando ceros a la izquierda y caracteres no numéricos).
- **FR-019**: El sistema DEBE conservar el seguimiento histórico de pedidos/líneas que ya no
  están en `PL`/`PP` o que ya no vienen en el ERP, sin mostrarlos en la vista principal.
- **FR-020**: La lista de pedidos DEBE permitir filtrar por destino, por estado (`PL`/`PP`) y
  por estado de revisión, y buscar por consecutivo o NIT.

### Key Entities *(include if feature involves data)*

- **Línea de pedido (ERP)**: una fila de detalle de un pedido de venta traída del ERP.
  Atributos: `pedido`, `bodega`, `item`, `descripcion`, `grupo_item`, `fecha_elaboracion`,
  `unidad_medida`, `cantidad_pedida`, `cantidad_despachada`, `fecha_requerida`,
  `fecha_despacho`, `precio_total_neto`, `nit_cliente`, `nombre_cliente`, `estado`,
  `fecha_ultimo_movimiento`, `usuario`. Clave natural `(pedido, item)`.
- **Cliente**: mapa NIT → nombre → **destino** (país). Atributos: `nit_cliente` (único),
  `cliente`, `destino`.
- **Parámetro de ítem**: atributos logísticos por ítem. Relevante: `item` (único),
  `descripcion`, `factor_kg` (kg de producto por unidad de venta), `categoria`, `embalaje`,
  `volumen`, `num_tendidos`, `cajas_pallet`, `altura_caja`, `cajas_por_nivel`.
- **Seguimiento de línea**: dato propio de la app, uno por `(pedido, item)`. Atributos:
  `pedido`, `item`, `descripcion`, `cantidad`, `estado_materiales`, `observaciones_plan`,
  `fecha_tentativa_cargue`, `estado_seguimiento`, `actualizado_por` (usuario), timestamps.
  Derivados: `alerta`, `comentario_correo`.
- **Pedido (vista agregada)**: no es tabla; es la agregación de líneas por `pedido` con
  cantidad total, toneladas totales, fecha de solicitud, cliente, destino, estado y estado de
  revisión.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Un planeador obtiene la lista de pedidos por liberar con sus totales en menos de
  3 segundos y sin ejecutar ninguna consulta manual ni abrir Excel.
- **SC-002**: El 100% de las toneladas por línea y por pedido coinciden con el cálculo
  `cantidad_pedida × factor_kg / 1000` para los ítems con factor conocido (verificado por
  pruebas automatizadas sobre datos del dominio).
- **SC-003**: Ninguna línea con `item` que empiece por `7` aparece en el listado, el detalle o
  los agregados.
- **SC-004**: Re-importar el mismo archivo Excel no cambia el conteo de filas de ninguna tabla
  espejo y conserva el 100% de los registros de seguimiento.
- **SC-005**: Un usuario sin el permiso correspondiente no puede leer ni modificar datos de la
  feature (respuesta `403` en el 100% de los intentos).
- **SC-006**: El cuerpo del correo de cargues incluye exactamente las líneas con
  `fecha_tentativa_cargue` y ninguna sin ella.
- **SC-007**: Para cada pedido con seguimiento parcial, el estado de revisión mostrado es
  `REVISADO`; para los que no tienen ninguno, `POR REVISAR` (100% de coincidencia).

## Assumptions

- La **fecha de solicitud** del pedido equivale al `MIN(fecha_elaboracion)` de sus líneas
  (en el MVP: `Liberación_Pedido!D6 = F13`, la fecha de la primera línea).
- La **cantidad total** del pedido se expresa en **unidades de venta** (cajas), igual que
  `Liberación_Pedido!D10 = SUM(H:H)` del MVP; no se convierte a otra unidad.
- El `factor_kg` de Parametros son **kilogramos de producto por unidad de venta** (por caja);
  por eso las toneladas son `cantidad_pedida × factor_kg / 1000`.
- Los estados relevantes del ERP son `PL` y `PP` (el MVP los trata juntos como "por liberar").
  Otros estados vistos (`CO`, `CE`) se ignoran en esta feature.
- Los datos llegan por importación del Excel; no hay conexión directa al ERP en esta fase
  (Principio II de la constitución).
- La autenticación y el catálogo de permisos los provee la feature `002-admin-roles-permisos`;
  esta feature asume que existen los permisos `pedidos.ver`, `seguimiento.editar`,
  `correo.generar` y `erp.importar`.
- El envío del correo no se automatiza: la app entrega el cuerpo para copiar/enviar
  manualmente.
- Sólo un idioma (español) y un huso horario (America/Bogota) en v1.
- Volumen esperado: cientos de pedidos activos y ~2.000 líneas; no requiere paginación
  compleja ni caché en v1 (pero la lista sí pagina de a 50).

## Dependencies

- Feature `002-admin-roles-permisos` (autenticación + `spatie/laravel-permission` + catálogo de
  permisos y roles).
- Paquete `maatwebsite/excel` para la importación.
- Base de datos MySQL configurada (`DB_CONNECTION=mysql`).
- Archivos fuente disponibles: `Q:\Consulta SQL Pedidos-Despachos.xlsx` (datos) y, como
  referencia funcional, `Q:\Pedidos-Despachos de Venta EXP.xlsm` (MVP).
