# Quickstart — Seguimiento de Pedidos por Liberar

## Requisitos previos

- PHP 8.3 + Composer (Laragon).
- MySQL 8 en marcha; base `seguimiento_pedidos` creada.
- Feature `002-admin-roles-permisos` implementada (auth + permisos + roles sembrados).
- Archivo `Q:\Consulta SQL Pedidos-Despachos.xlsx` accesible.

## Puesta en marcha

```bash
composer require maatwebsite/excel
```

Editar `.env`:

```
APP_NAME="Seguimiento de Pedidos"
APP_LOCALE=es
APP_TIMEZONE=America/Bogota
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=seguimiento_pedidos
DB_USERNAME=root
DB_PASSWORD=
```

```bash
php artisan migrate
php artisan db:seed --class=RolesPermisosSeeder   # de la feature 002 (crea admin + roles)
php artisan erp:importar "Q:\Consulta SQL Pedidos-Despachos.xlsx"
npm install && npm run build   # assets (Tailwind/Vite)
php artisan serve
```

## Verificación manual (mapea a los Success Criteria del spec)

1. **Importación (SC-004)**
   ```bash
   php artisan erp:importar "Q:\Consulta SQL Pedidos-Despachos.xlsx"
   ```
   - El resumen reporta filas por hoja (~15 clientes, ~244 parámetros, N líneas).
   - Ejecutar de nuevo: los conteos por tabla no cambian.

2. **Filtro PL/PP + exclusión ítems `7x` (SC-003)**
   - Entrar a `/pedidos-liberar` con un usuario con `pedidos.ver`.
   - Todos los pedidos listados tienen estado `PL` o `PP`.
   - Ningún detalle muestra ítems que empiecen por `7`.

3. **Toneladas (SC-002)**
   - Abrir un pedido con el ítem `001131` (`factor_kg = 0.549125`), `cantidad_pedida = 2400`.
   - Toneladas de la línea = `1.31790`.
   - El total del pedido = suma de las toneladas de sus líneas.
   - Si un ítem no tiene parámetro: toneladas vacías + "sin factor"; total marcado "incompleto".

4. **Agregados por pedido**
   - Cantidad total = suma de `cantidad pedida` (unidades).
   - Fecha de solicitud = fecha de elaboración más antigua entre las líneas.
   - Destino = país del cliente según su NIT (p. ej. `B66061078 → ESPAÑA`); sin cliente → `#N/D`.

5. **Captura de seguimiento (SC-007)**
   - Con `seguimiento.editar`: en el detalle, llenar `ESTADO MATERIALES`, `OBSERVACIONES PLAN`,
     `FECHA TENTATIVA DE CARGUE` y `ESTADO` de una línea; guardar.
   - Recargar: los valores persisten; el pedido pasa a `REVISADO`.
   - Volver a guardar con otra fecha: se actualiza el mismo registro (no se duplica).

6. **Semáforo y comentario**
   - `Pendiente → 🔴`, `En validación → 🟡`, `Listo para cargar → ✅`.
   - Sin fecha tentativa → `PENDIENTE POR DEFINIR FECHA`.
   - Con fecha `2026-08-26` → `Pedido disponible para cargue sobre el 26/08/2026`.

7. **Correo de cargues (SC-006)**
   - Con `correo.generar`: abrir `/cargues/correo`.
   - Aparecen sólo las líneas con fecha tentativa, agrupadas por pedido, con su frase.
   - Hay versión HTML y versión de texto para copiar.

8. **Autorización (SC-005)**
   - Usuario sin `pedidos.ver`: `/pedidos-liberar` → `403`.
   - Usuario sin `seguimiento.editar`: campos de seguimiento de sólo lectura; `PUT` → `403`.
   - Usuario sin `correo.generar`: `/cargues/correo` → `403`.

## Pruebas automatizadas

```bash
php artisan test --filter="Toneladas|SeguimientoDerivados|PedidosPorLiberarQuery|ImportarErpExcel|GuardarSeguimiento|CorreoCargues|AutorizacionPedidos"
vendor/bin/pint --test
```

Todas en verde y sin cambios de estilo pendientes.
