# Data Model: Seguimiento de Pedidos por Liberar

Convenciones: nombres en español; `item` y `nit_cliente` **siempre `string`** (preservan ceros
a la izquierda y caracteres no numéricos); fechas como `date`; dinero/decimales como `DECIMAL`.

## Tabla `lineas_pedido` (espejo del ERP — hoja `PedidosVenta` / `Pedidos Liberar`)

| Columna | Tipo | Nulo | Origen (encabezado Excel) | Notas |
|---|---|---|---|---|
| `id` | bigint PK | no | — | autoincrement |
| `pedido` | string(20) | no | `PEDIDO` | consecutivo |
| `bodega` | string(10) | sí | `BODEGA` | |
| `item` | string(30) | no | `ITEM` | texto; clave natural junto con `pedido` |
| `descripcion` | string(255) | sí | `DESCRIPCION` | |
| `grupo_item` | string(120) | sí | `GRUPOITEM` | |
| `fecha_elaboracion` | date | sí | `FECHAELABORACION` | base de la fecha de solicitud |
| `unidad_medida` | string(10) | sí | `UNIDADMEDIDA` | `UN` / `KG` |
| `cantidad_pedida` | decimal(16,3) | no | `CANTIDADPEDIDA` | |
| `cantidad_despachada` | decimal(16,3) | sí | `CANTIDADDESPACHADA` | |
| `fecha_requerida` | date | sí | `FECHAREQUERIDA` | |
| `fecha_despacho` | date | sí | `FECHADESPACHO` | |
| `precio_total_neto` | decimal(18,4) | sí | `PRECIOTOTALNETO` | |
| `nit_cliente` | string(30) | no | `NITCLIENTE` | texto; cruza con `clientes` |
| `nombre_cliente` | string(255) | sí | `NOMBRECLIENTE` | denormalizado del ERP |
| `estado` | string(4) | no | `ESTADO` | `PL`,`PP`,`CO`,`CE`,… |
| `fecha_ultimo_movimiento` | date | sí | `FECHAULTIMOMOVIMIENTO` | |
| `usuario` | string(40) | sí | `USUARIO` | usuario del ERP |
| `activo` | boolean | no | — | `false` si el `(pedido,item)` dejó de venir del ERP |
| `importado_en` | timestamp | no | — | marca de la última importación que lo tocó |
| `created_at` / `updated_at` | timestamp | sí | — | |

Índices: único `(pedido, item)`; índice `estado`; índice `nit_cliente`; índice `activo`.

## Tabla `clientes` (hoja `Clientes`)

| Columna | Tipo | Nulo | Origen | Notas |
|---|---|---|---|---|
| `id` | bigint PK | no | — | |
| `nit_cliente` | string(30) | no | `NitCliente` | **único** |
| `cliente` | string(255) | sí | `Cliente` | razón social |
| `destino` | string(80) | sí | `Destino` | país (CUBA, ESPAÑA, CHILE, …) |
| `created_at` / `updated_at` | timestamp | sí | — | |

Índice: único `nit_cliente`.

## Tabla `parametros_item` (hoja `Parametros`)

| Columna | Tipo | Nulo | Origen | Notas |
|---|---|---|---|---|
| `id` | bigint PK | no | — | |
| `item` | string(30) | no | `Item` | **único**; texto |
| `descripcion` | string(255) | sí | `Descripcion` | |
| `factor_kg` | decimal(14,6) | sí | `Factor kg` | kg de producto por unidad de venta |
| `categoria` | string(80) | sí | `Categoria` | |
| `embalaje` | integer | sí | `Embalaje` | unidades por caja |
| `volumen` | decimal(16,4) | sí | `Volumen` | |
| `num_tendidos` | integer | sí | `#Tendidos` | |
| `cajas_pallet` | integer | sí | `Cajas Pallet` | |
| `altura_caja` | decimal(8,2) | sí | `Altura Caja` | |
| `cajas_por_nivel` | decimal(10,4) | sí | `Cajaspor nivel` | |
| `created_at` / `updated_at` | timestamp | sí | — | |

Índice: único `item`.

> **Seguimiento en dos niveles.** `fecha_tentativa_cargue`, `estado_seguimiento` y la `alerta`
> son a nivel de **pedido** (`seguimiento_pedidos`). `estado_materiales`, `observaciones`,
> `fecha_programa` e `inventario` son a nivel de **línea** (`seguimientos`).

## Tabla `seguimiento_pedidos` (dato propio — nivel PEDIDO)

| Columna | Tipo | Nulo | Notas |
|---|---|---|---|
| `id` | bigint PK | no | |
| `pedido` | string(20) | no | **único** |
| `estado_seguimiento` | enum | no | `Pendiente` \| `En validación` \| `Listo para cargar` (default `Pendiente`) |
| `fecha_tentativa_cargue` | date | sí | alimenta `alerta` / `comentario_correo` y el correo de cargues |
| `actualizado_por` | bigint FK→`users.id` | sí | último usuario que guardó |
| `created_at` / `updated_at` | timestamp | sí | trazabilidad |

**Derivados (modelo `SeguimientoPedido`):**

- `alerta`: `Pendiente→🔴`, `En validación→🟡`, `Listo para cargar→✅`, vacío→`""`.
- `comentario_correo`: sin `fecha_tentativa_cargue` → `"PENDIENTE POR DEFINIR FECHA"`;
  con fecha → `"Pedido disponible para cargue sobre el "` + `d/m/Y`.
- Estado de revisión del pedido: `REVISADO` si existe fila en `seguimiento_pedidos`.

## Tabla `seguimientos` (dato propio — nivel LÍNEA; reemplaza `BD_Seguimiento`)

| Columna | Tipo | Nulo | Notas |
|---|---|---|---|
| `id` | bigint PK | no | |
| `pedido` | string(20) | no | clave natural con `item` |
| `item` | string(30) | no | texto |
| `descripcion` | string(255) | sí | copia de la descripción de la línea al momento de captura |
| `cantidad` | decimal(16,3) | sí | copia de `cantidad_pedida` al momento de captura (histórico) |
| `estado_materiales` | string(120) | sí | **lista cerrada** (enum `EstadoMateriales`): `PEND. MATERIAL DE EMPAQUE`, `PEND. MATERIAL MATERIA PRIMA`, `PEND. REQUISICION`, `PEND. ORDEN DE COMPRA`, `OC ENVIADA A PROVEEDOR` |
| `observaciones` | text | sí | texto libre (antes `observaciones_plan`) |
| `fecha_programa` | date | sí | fecha programada elegida por planeación |
| `inventario` | decimal(16,3) | sí | unidades disponibles; base del `porcentaje_cumplimiento` |
| `actualizado_por` | bigint FK→`users.id` | sí | último usuario que guardó |
| `created_at` / `updated_at` | timestamp | sí | trazabilidad |

Índice: único `(pedido, item)`.

**Derivado (modelo `Seguimiento`):**

- `porcentaje_cumplimiento` = `inventario / cantidad × 100` (null si `inventario` es null o
  `cantidad` = 0). A nivel de pedido: `Σ inventario / Σ cantidad_pedida × 100`.

## Entidad derivada: "Pedido por liberar" (no es tabla)

Agregación de `lineas_pedido` (universo `estado ∈ {PL,PP}`, `item NOT LIKE '7%'`, `activo`):

| Campo | Cálculo |
|---|---|
| `pedido` | agrupador |
| `nit_cliente`, `nombre_cliente` | de las líneas |
| `destino` | `clientes.destino` por `nit_cliente`, o `#N/D` |
| `estado` | `PL`/`PP` |
| `fecha_solicitud` | `MIN(fecha_elaboracion)` |
| `cantidad_total` | `SUM(cantidad_pedida)` |
| `toneladas_total` | `SUM(cantidad_pedida × parametros_item.factor_kg) / 1000` |
| `toneladas_incompleto` | `TRUE` si alguna línea del pedido no tiene `factor_kg` |
| `estado_revision` | `REVISADO` si existe algún `seguimientos` del `pedido`, si no `POR REVISAR` |

## Relaciones

```
lineas_pedido.nit_cliente  ──(string =)──▶  clientes.nit_cliente        [left join]
lineas_pedido.item         ──(string =)──▶  parametros_item.item        [left join]
lineas_pedido.(pedido,item)──(string =)──▶  seguimientos.(pedido,item)  [left join]
lineas_pedido.pedido       ──(string =)──▶  seguimiento_pedidos.pedido  [left join]
seguimientos.actualizado_por ─────────────▶  users.id                   [belongs to]
seguimiento_pedidos.actualizado_por ──────▶  users.id                   [belongs to]
```

## Reglas de validación (componentes Volt `pedidos/show`)

**Nivel pedido (`guardarPedido`):**

- `estadoSeguimiento`: `required|in:Pendiente,En validación,Listo para cargar`
- `fechaTentativa`: `nullable|date`

**Nivel línea (`guardarLineas`):**

- `lineasSeg.*.estado_materiales`: `nullable` + `in:` (los 5 valores de `EstadoMateriales`)
- `lineasSeg.*.observaciones`: `nullable|string`
- `lineasSeg.*.fecha_programa`: `nullable|date`
- `lineasSeg.*.inventario`: `nullable|numeric|min:0`
- Sólo se persisten los `item` que existen en `lineas_pedido` con `activo = true` y
  `estado ∈ {PL,PP}` para ese `pedido`.
