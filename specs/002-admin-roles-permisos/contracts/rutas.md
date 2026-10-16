# Contrato de Rutas HTTP — Administración de Roles y Permisos

Respuestas HTML (Blade). `guest` = sólo no autenticados; `auth` = autenticado; `activo` =
`AsegurarUsuarioActivo`.

## Autenticación

### GET `/login` — `login` · middleware `guest`
- 200: vista `auth/login`. Si ya autenticado → `302` a `pedidos.index`.

### POST `/login` · middleware `guest`
- Body: `email` (required, email), `password` (required), `remember` (opcional, boolean).
- Usuario inactivo → `302 back` con error "Tu usuario está inactivo. Contacta al administrador."
- Credenciales inválidas → `302 back` con error genérico "Credenciales inválidas.".
- Éxito → regenera sesión, `302` a `intended` o `pedidos.index`.

### POST `/logout` — `logout` · middleware `auth`
- Invalida sesión + regenera token. `302` a `/login`.

### (No existen) `/register`, `/password/*`, `/email/verify/*`
- Cualquier acceso → `404`.

## Administración — prefijo `/admin`, nombre `admin.`, middleware `auth` + `activo`

### Usuarios — middleware `can:admin.usuarios`

| Método | URI | Nombre | Acción |
|---|---|---|---|
| GET | `/admin/usuarios` | `admin.usuarios.index` | lista + filtros (`rol`, `estado`, `q`) |
| GET | `/admin/usuarios/create` | `admin.usuarios.create` | formulario |
| POST | `/admin/usuarios` | `admin.usuarios.store` | crear (`GuardarUsuarioRequest`) |
| GET | `/admin/usuarios/{usuario}/edit` | `admin.usuarios.edit` | formulario |
| PUT/PATCH | `/admin/usuarios/{usuario}` | `admin.usuarios.update` | actualizar |
| DELETE | `/admin/usuarios/{usuario}` | `admin.usuarios.destroy` | borrar (rechaza último `Administrador`) |
| PUT | `/admin/usuarios/{usuario}/password` | `admin.usuarios.password` | reset de contraseña (`min:8`) |
| PUT | `/admin/usuarios/{usuario}/activo` | `admin.usuarios.activo` | activar/desactivar (rechaza último `Administrador` activo) |

- Éxito → `302` al `index` con flash. Validación → `422`/`back`. Sin permiso → `403`.
- `store`/`update`: `syncRoles($request->roles)`. `update` con `password` vacío no cambia clave.

### Roles — middleware `can:admin.roles`

| Método | URI | Nombre | Acción |
|---|---|---|---|
| GET | `/admin/roles` | `admin.roles.index` | lista (con conteos de usuarios/permisos) |
| GET | `/admin/roles/create` | `admin.roles.create` | formulario |
| POST | `/admin/roles` | `admin.roles.store` | crear (`GuardarRolRequest`) |
| GET | `/admin/roles/{role}/edit` | `admin.roles.edit` | formulario (checkboxes del catálogo) |
| PUT/PATCH | `/admin/roles/{role}` | `admin.roles.update` | `syncPermissions($request->permisos)` + `forgetCachedPermissions()` |
| DELETE | `/admin/roles/{role}` | `admin.roles.destroy` | rechaza `Administrador` (protegido) y roles con usuarios |
| GET | `/admin/permisos` | `admin.permisos.index` | matriz permiso × rol, **sólo lectura** |

- Sin permiso → `403`. Borrado no permitido → `302 back` con error.

## Comportamiento transversal

- Toda ruta fuera de `guest` sin sesión → `302` a `/login` (guarda `intended`).
- Usuario que se desactiva a sí mismo mientras tiene sesión → en la siguiente request el
  middleware `activo` lo desloguea.
- Menú de navegación: entradas `Usuarios` / `Roles` visibles sólo con el `@can` respectivo.
- Todos los cambios de administración se registran en el log (`admin.usuario.creado`,
  `admin.rol.permisos_actualizados`, …) con `actor` y timestamp.
