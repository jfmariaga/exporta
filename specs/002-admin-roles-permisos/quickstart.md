# Quickstart — Administración de Roles y Permisos

## Puesta en marcha

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

`.env` (además de la config de MySQL de la feature 001):

```
ADMIN_EMAIL=admin@levapan.com
ADMIN_PASSWORD=cambiar123
```

```bash
php artisan migrate                                   # tablas de spatie + activo en users
php artisan db:seed --class=RolesPermisosSeeder       # permisos, roles base y admin inicial
php artisan serve
```

## Verificación manual (mapea a los Success Criteria)

1. **Seeder idempotente (SC-005)**
   - `php artisan db:seed --class=RolesPermisosSeeder` dos veces.
   - `php artisan tinker` → `Spatie\Permission\Models\Permission::count()` = 6;
     `Spatie\Permission\Models\Role::count()` = 3; `User::count()` sin cambios en la 2ª corrida.

2. **Login / logout (SC-004)**
   - Ir a `/login`, entrar con `ADMIN_EMAIL` / `ADMIN_PASSWORD` → redirige a `/pedidos-liberar`.
   - Cerrar sesión → vuelve a `/login`.
   - Marcar el admin como inactivo (desde otro admin o tinker) e intentar entrar → "usuario
     inactivo".
   - Sin sesión, abrir `/admin/usuarios` → redirige a `/login`.
   - Abrir `/register` → `404` (no existe).

3. **CRUD de usuarios (SC-002)**
   - Con el admin: crear usuario "Planeador 1" con rol `Planeador`.
   - Entrar como "Planeador 1": puede ver `/pedidos-liberar`, editar seguimiento y generar
     correo; **no** puede abrir `/admin/usuarios` (`403`) ni importar.
   - Cambiarle el rol a `Consulta`: ahora sólo puede ver pedidos; editar seguimiento → `403`.

4. **CRUD de roles y efecto en caliente (SC-006)**
   - Con el admin: al rol `Consulta` agregarle `correo.generar` y guardar.
   - Con la sesión de "Planeador 1" (rol `Consulta`) **ya abierta**, recargar `/cargues/correo`
     → ahora responde `200` sin haber cerrado sesión.
   - Quitar el permiso de nuevo → siguiente request vuelve a `403`.

5. **Regla del último administrador (SC-003)**
   - Con un solo usuario `Administrador`: intentar desactivarlo, borrarlo o quitarle el rol
     → todas las acciones se rechazan con mensaje.
   - Crear un segundo `Administrador`: ahora sí se puede desactivar el primero.

6. **Catálogo de permisos sólo lectura (SC / FR-009)**
   - Abrir `/admin/permisos`: matriz permiso × rol, sin botones de crear/editar/borrar permiso.

7. **Roles protegidos**
   - Intentar borrar el rol `Administrador` → rechazado.
   - Intentar borrar un rol asignado a usuarios → rechazado / pide reasignar.

8. **Contraseñas (SC-007)**
   - Revisar respuestas HTML y `storage/logs/laravel.log`: ninguna contraseña en texto plano.

## Pruebas automatizadas

```bash
php artisan test --filter="LoginTest|UsuarioCrudTest|RolCrudTest|UltimoAdministradorTest|PermisosDeRolTest|RolesPermisosSeederTest"
vendor/bin/pint --test
```
