# Data Model: Administración de Roles y Permisos

Base: tablas estándar de `spatie/laravel-permission` (guard `web`) + ajustes a `users`.

## Tabla `users` (existente — se amplía)

| Columna | Tipo | Nulo | Notas |
|---|---|---|---|
| `id` | bigint PK | no | |
| `name` | string | no | |
| `email` | string | no | **único** (case-insensitive) |
| `email_verified_at` | timestamp | sí | sin uso en v1 |
| `password` | string | no | hash bcrypt/argon2 |
| `activo` | boolean | no | **nuevo**; default `true`; index. Inactivo no inicia sesión |
| `password_cambiada_en` | timestamp | sí | **nuevo, opcional**; `null` fuerza cambio de la provisional |
| `remember_token` | string | sí | "recordarme" |
| `created_at` / `updated_at` | timestamp | sí | |

Migración nueva: `add_activo_to_users_table` (añade `activo` y, opcional, `password_cambiada_en`).

## Tablas de `spatie/laravel-permission` (publicadas)

### `permissions`
| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | uno de: `pedidos.ver`, `seguimiento.editar`, `correo.generar`, `erp.importar`, `admin.usuarios`, `admin.roles` |
| `guard_name` | string | `web` |
| timestamps | | |
Único `(name, guard_name)`. **Catálogo fijo** — sólo lo crea el seeder.

### `roles`
| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | `Administrador`, `Planeador`, `Consulta`, o los que cree el admin |
| `guard_name` | string | `web` |
| `protegido` | boolean | **añadido**; `true` para `Administrador` (no eliminable). Alternativa: lista blanca en código |
| timestamps | | |
Único `(name, guard_name)`.

### `model_has_roles`  (usuario ↔ rol)
`role_id`, `model_type`, `model_id`  — PK compuesta. `model_type = App\Models\User`.

### `model_has_permissions`  (usuario ↔ permiso directo)
`permission_id`, `model_type`, `model_id`. **No se usa en v1** (los permisos se dan sólo vía rol).

### `role_has_permissions`  (rol ↔ permiso)
`permission_id`, `role_id` — PK compuesta.

## Relación con la feature 001

```
users.id  ◀── seguimientos.actualizado_por   (belongsTo; onDelete: nullOnDelete o usuario "Eliminado")
```

Al borrar un usuario, los registros de `seguimientos` se conservan (FR-016 de esta feature,
Principio VII de la constitución).

## Enums / constantes

- `App\Support\Permisos` — constantes de los 6 permisos + `Permisos::TODOS`.
- `App\Support\RolesBase` (opcional) — `ADMINISTRADOR = 'Administrador'`, etc., usado sólo por
  el seeder y la regla de "último administrador".

## Reglas de validación

### `LoginRequest`
- `email`: `required|email`
- `password`: `required|string`
- `remember`: `sometimes|boolean`

### `GuardarUsuarioRequest`
- `name`: `required|string|max:255`
- `email`: `required|email|max:255|unique:users,email,{id}`
- `password`: `required_without:id|nullable|string|min:8|confirmed` (en create requerido; en edit opcional)
- `roles`: `required|array|min:1`
- `roles.*`: `string|exists:roles,name`

### `GuardarRolRequest`
- `name`: `required|string|max:255|unique:roles,name,{id}`
- `permisos`: `sometimes|array`
- `permisos.*`: `string|in:pedidos.ver,seguimiento.editar,correo.generar,erp.importar,admin.usuarios,admin.roles`

### Reglas de integridad (servicio `AdministradorGuard`)
- No desactivar / borrar / quitar el rol `Administrador` si es el **único** usuario con ese rol
  **activo**.
- No eliminar el rol `Administrador` (`protegido`).
- No eliminar un rol con usuarios asignados sin reasignación explícita.
