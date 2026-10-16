# Contrato de Rutas HTTP — Seguimiento de Pedidos por Liberar

Todas bajo middleware `auth`. Respuestas HTML (Blade). Errores de permiso → `403`.

## GET `/pedidos-liberar` — `pedidos.index`

- **Permiso**: `pedidos.ver`
- **Query params** (todos opcionales): `destino`, `estado` (`PL`|`PP`), `revision`
  (`REVISADO`|`POR REVISAR`), `q` (busca en `pedido` o `nit_cliente`), `page`.
- **Respuesta 200**: vista `pedidos-liberar/index` con lista paginada (50/pág). Cada fila:
  `pedido`, `nit_cliente`, `nombre_cliente`, `destino`, `fecha_solicitud`, `cantidad_total`,
  `toneladas_total`, `toneladas_incompleto` (bool), `estado`, `estado_revision`.

## GET `/pedidos-liberar/{pedido}` — `pedidos.show`

- **Permiso**: `pedidos.ver`
- **Path**: `{pedido}` = consecutivo.
- **Respuesta 200**: vista `pedidos-liberar/show` con:
  - Cabecera: `nit_cliente`, `nombre_cliente`, `destino`, `estado`, `fecha_solicitud`,
    `cantidad_total`, `toneladas_total`, `toneladas_incompleto`, `estado_revision`.
  - `lineas[]`: `item`, `descripcion`, `grupo_item`, `unidad_medida`, `cantidad_pedida`,
    `cantidad_despachada`, `fecha_requerida`, `factor_kg`, `toneladas_linea`, `sin_factor`,
    y del seguimiento: `estado_materiales`, `observaciones_plan`, `fecha_tentativa_cargue`,
    `estado_seguimiento`, `alerta`, `comentario_correo`.
- **404** si el pedido no tiene líneas en el universo `PL`/`PP` sin ítems `7x`.

## PUT `/pedidos-liberar/{pedido}/seguimiento` — `pedidos.seguimiento`

- **Permiso**: `seguimiento.editar`
- **Body** (form): arreglo `lineas` indexado por `item`:
  ```
  lineas[001131][estado_materiales]      = "OK"
  lineas[001131][observaciones_plan]     = "Por programar"
  lineas[001131][fecha_tentativa_cargue] = "2026-08-26"
  lineas[001131][estado_seguimiento]     = "Listo para cargar"
  ```
  (También se acepta el submit de una sola línea con esos 4 campos planos.)
- **Validación**: ver `data-model.md` (`GuardarSeguimientoRequest`). Cada `item` debe
  pertenecer al `{pedido}`, estar `activo` y en estado `PL`/`PP`.
- **Efecto**: `upsert` en `seguimientos` por `(pedido, item)`; setea `actualizado_por` =
  `auth()->id()` y `updated_at`. Copia `descripcion` y `cantidad` de la línea vigente.
- **Respuesta**: `302` redirect a `pedidos.show` con `status` de éxito.
- **422** si falla validación. **403** sin permiso.

## GET `/cargues/correo` — `cargues.correo`

- **Permiso**: `correo.generar`
- **Query params**: `destino` (opcional), `desde`/`hasta` (filtran `fecha_tentativa_cargue`).
- **Respuesta 200**: vista `cargues/correo` con:
  - `html`: bloque HTML listo para pegar (tabla por pedido).
  - `texto`: versión de texto plano.
  - Sólo líneas con `fecha_tentativa_cargue` no nula dentro del universo `PL`/`PP` sin `7x`.

## POST `/importaciones` — `importaciones.store`

- **Permiso**: `erp.importar`
- **Body** (multipart): `archivo` = `.xlsx` (`required|file|mimes:xlsx,xls|max:20480`).
- **Efecto**: guarda en `storage/app/importaciones/` y ejecuta el mismo servicio que
  `php artisan erp:importar`. Transaccional. No toca `seguimientos`.
- **Respuesta**: `302` redirect con resumen `hoja | insertadas | actualizadas | desactivadas`.
- **422** si el archivo o las hojas no son válidos (sin dejar la base a medias).

## Comando CLI (equivalente a `importaciones.store`)

```
php artisan erp:importar "Q:\Consulta SQL Pedidos-Despachos.xlsx"
```

- Hojas: `PedidosVenta` (o `Pedidos Liberar`) → `lineas_pedido`; `Clientes` → `clientes`;
  `Parametros` (o `Parámetros`) → `parametros_item`.
- Salida: tabla resumen por hoja. Código de salida `0` OK, `1` error de validación.
