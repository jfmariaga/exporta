# Implementation Plan: Administración de Roles y Permisos

**Branch**: `002-admin-roles-permisos` | **Date**: 2026-08-28 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-admin-roles-permisos/spec.md`

## Summary

Añadir autenticación mínima propia (login/logout, sin registro público, bloqueo de inactivos) y
un módulo de administración de **usuarios**, **roles** y **permisos** sobre
`spatie/laravel-permission`. Las rutas de negocio se protegen con `can:<permiso>`. Un seeder
idempotente crea el catálogo de permisos, los roles base (`Administrador`, `Planeador`,
`Consulta`) y un usuario administrador inicial. Reglas de seguridad: nunca dejar el sistema sin
un `Administrador` activo; catálogo de permisos de sólo lectura en UI.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 12.68

**Primary Dependencies**: `spatie/laravel-permission`, Blade + Tailwind CSS v4 + Vite 7

**Storage**: MySQL 8. Tablas de spatie + columna `activo` en `users`.

**Testing**: PHPUnit 11 (`php artisan test`). Feature tests para login, CRUD, autorización y
seeder. `laravel/pint`.

**Target Platform**: App web server-rendered (guard `web`, sesión).

**Project Type**: Web application (monolito Laravel).

**Performance Goals**: N/A (decenas de usuarios). Cache de permisos de spatie por defecto.

**Constraints**: Autorización por permiso, no por rol (Principio V). Sin Breeze/Jetstream
(constitución, Restricciones Técnicas). Dominio/UI en español (Principio VI). Trazabilidad
(Principio VII): log de cambios de usuarios/roles.

**Scale/Scope**: 2 migraciones propias + migraciones de spatie, 4 controladores, ~8 vistas
Blade, 1 middleware, 1 seeder, ~12 pruebas.

## Constitution Check

| Principio | Cumplimiento |
|---|---|
| I. Stack fijo (Blade) | Login y admin en Blade + Tailwind; sin JS framework. ✅ |
| II. ERP sólo lectura | No aplica (no toca ERP). ✅ |
| III. Importación idempotente | El seeder de roles/permisos es idempotente. ✅ |
| IV. Reglas con pruebas primero | Pruebas de "último administrador", "permisos exactos de rol", "inactivo no entra" antes de UI. ✅ |
| V. Autorización por permiso | `can:<permiso>` en todas las rutas; el código nunca compara `hasRole('X')` para autorizar negocio (sólo para reglas de protección del rol `Administrador`). ✅ |
| VI. Dominio en español | Rutas `/admin/usuarios`, `/admin/roles`; enums y textos en español. ✅ |
| VII. Trazabilidad | Log de eventos de administración con actor + timestamp. ✅ |

**Sin violaciones.**

> Nota justificada: la regla "no dejar el sistema sin `Administrador`" **sí** inspecciona el rol
> `Administrador` por nombre. Esto es una **regla de integridad**, no autorización de negocio, y
> es coherente con el Principio V (que prohíbe autorizar *funcionalidad* por rol).

## Project Structure

### Documentation (this feature)

```text
specs/002-admin-roles-permisos/
├── spec.md
├── plan.md              # Este archivo
├── data-model.md        # Tablas, columnas y pivotes
├── quickstart.md        # Puesta en marcha y verificación
├── contracts/
│   └── rutas.md         # Contrato de rutas de auth y /admin
└── tasks.md             # Generado por /speckit-tasks
```

### Source Code (repository root)

```text
app/
├── Models/
│   └── User.php                       # + HasRoles, + 'activo', + scope activos()
├── Http/
│   ├── Controllers/
│   │   ├── Auth/LoginController.php    # showLogin, login, logout
│   │   └── Admin/
│   │       ├── UsuarioController.php   # index, create, store, edit, update, destroy, resetPassword, toggleActivo
│   │       └── RolController.php       # index, create, store, edit, update, destroy  (+ index de permisos)
│   ├── Middleware/
│   │   └── AsegurarUsuarioActivo.php   # cierra sesión / bloquea si !activo
│   └── Requests/
│       ├── Auth/LoginRequest.php
│       ├── Admin/GuardarUsuarioRequest.php
│       └── Admin/GuardarRolRequest.php
├── Support/
│   └── Permisos.php                    # constantes: PEDIDOS_VER, SEGUIMIENTO_EDITAR, ... + catálogo
└── Policies/  (opcional)
    └── (no se usan policies; se usa can:<permiso> directo)

database/
├── migrations/
│   ├── ####_add_activo_to_users_table.php
│   └── (migraciones publicadas de spatie/laravel-permission)
└── seeders/
    └── RolesPermisosSeeder.php

config/permission.php                   # publicado de spatie

resources/views/
├── auth/login.blade.php
└── admin/
    ├── usuarios/{index,create,edit}.blade.php
    ├── roles/{index,create,edit}.blade.php
    └── permisos/index.blade.php

routes/web.php                          # rutas de auth + grupo /admin

tests/Feature/
├── Auth/LoginTest.php
├── Admin/UsuarioCrudTest.php
├── Admin/RolCrudTest.php
├── Admin/UltimoAdministradorTest.php
├── Admin/PermisosDeRolTest.php
└── RolesPermisosSeederTest.php
```

**Structure Decision**: Monolito Laravel. Autenticación hecha a mano (sin paquete de
scaffolding): `LoginController` + `Auth::attempt` + guard `web`. `spatie/laravel-permission`
sólo para roles/permisos. Sin policies: se usa `->middleware('can:<permiso>')` y `@can` en Blade.

## Diseño técnico

### Dependencias y publicación

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

- `User` implementa `Spatie\Permission\Traits\HasRoles`.
- `config/permission.php` por defecto (cache habilitada).

### Migración `add_activo_to_users_table`

- `activo` boolean, default `true`, index.
- (Opcional) `password_cambiada_en` timestamp nullable para forzar cambio de la contraseña
  provisional.

### `app/Support/Permisos.php`

```php
final class Permisos {
    const PEDIDOS_VER        = 'pedidos.ver';
    const SEGUIMIENTO_EDITAR = 'seguimiento.editar';
    const CORREO_GENERAR     = 'correo.generar';
    const ERP_IMPORTAR       = 'erp.importar';
    const ADMIN_USUARIOS     = 'admin.usuarios';
    const ADMIN_ROLES        = 'admin.roles';

    public const TODOS = [ self::PEDIDOS_VER, self::SEGUIMIENTO_EDITAR, self::CORREO_GENERAR,
                           self::ERP_IMPORTAR, self::ADMIN_USUARIOS, self::ADMIN_ROLES ];
}
```

### Autenticación

- `GET /login` → `LoginController@showLogin` (vista `auth/login`). Si ya autenticado → redirect.
- `POST /login` → `LoginController@login`:
  - `LoginRequest`: `email` (required, email), `password` (required), `remember` (boolean).
  - Busca usuario por email; si `!activo` → `back()` con error "usuario inactivo" (sin intentar
    `Auth::attempt`, o invalidando tras attempt).
  - `Auth::attempt(['email'=>..., 'password'=>...], $remember)`; regenerar sesión;
    redirect intended → `pedidos.index`.
  - Error genérico "Credenciales inválidas" (no distinguir email/clave).
- `POST /logout` → invalidar sesión + token; redirect a `/login`.
- Middleware `AsegurarUsuarioActivo` (en el grupo `auth`): si `auth()->user()->activo === false`
  → `Auth::logout()` + redirect login con mensaje.
- Sin rutas de registro / verificación / reseteo público.

### Administración de usuarios (`/admin/usuarios`, `can:admin.usuarios`)

- `index`: tabla (nombre, email, roles, activo, última actualización) + filtros por rol/estado.
- `create` / `store`: `GuardarUsuarioRequest` (`name` req; `email` req, `email`, `unique:users`;
  `password` req min 8 en create; `roles` req, array, `exists:roles,name`). Crea usuario
  `activo=true`, `password` hasheada, `syncRoles($roles)`.
- `edit` / `update`: como create; `password` opcional (si viene, se actualiza). `email` único
  ignorando el propio.
- `toggleActivo`: activa/desactiva. **Rechaza** desactivar si es el único `Administrador` activo.
- `resetPassword`: define nueva contraseña (min 8); opcional marcar `password_cambiada_en=null`.
- `destroy`: preferir desactivar; si se permite borrar, **rechaza** si es el último
  `Administrador`; los `seguimientos.actualizado_por` quedan con `nullOnDelete` o usuario
  "Eliminado".
- Regla **último administrador** centralizada en `AdministradorGuard::garantizarNoEsUltimo($user)`
  usada por `update`, `toggleActivo`, `destroy`.

### Administración de roles (`/admin/roles`, `can:admin.roles`)

- `index`: lista de roles con conteo de usuarios y de permisos.
- `create`/`store`/`edit`/`update`: `GuardarRolRequest` (`name` req, `unique:roles,name`
  ignorando propio; `permisos` array, `in:` catálogo). `syncPermissions($permisos)`.
- `destroy`: **rechaza** si `name === 'Administrador'` (protegido) o si tiene usuarios
  asignados (pide reasignar). 
- `permisos/index` (`can:admin.roles`): tabla del catálogo fijo (permiso → roles que lo tienen),
  **sólo lectura**.
- El rol `Administrador` se marca `protegido` (columna añadida a `roles` o lista blanca en
  código); su set de permisos se re-sincroniza a `Permisos::TODOS` en cada `seed`.

### Efecto de cambios en caliente (FR-014 / SC-006)

`spatie/laravel-permission` resuelve permisos por petición y cachea; tras `syncPermissions` se
llama `app(PermissionRegistrar::class)->forgetCachedPermissions()`. La prueba
`PermisosDeRolTest` verifica que un usuario ya logueado ve el permiso nuevo en la siguiente
request sin re-login.

### Seeder `RolesPermisosSeeder` (idempotente)

```
foreach (Permisos::TODOS as $p) Permission::findOrCreate($p, 'web');
$admin = Role::findOrCreate('Administrador','web'); $admin->syncPermissions(Permisos::TODOS);
$plan  = Role::findOrCreate('Planeador','web');     $plan->syncPermissions([PEDIDOS_VER, SEGUIMIENTO_EDITAR, CORREO_GENERAR]);
$cons  = Role::findOrCreate('Consulta','web');      $cons->syncPermissions([PEDIDOS_VER]);
$u = User::firstOrCreate(['email'=>env('ADMIN_EMAIL')], ['name'=>'Administrador','password'=>Hash::make(env('ADMIN_PASSWORD','cambiar123')), 'activo'=>true]);
$u->syncRoles(['Administrador']);
```

Registrar en `DatabaseSeeder`. Re-ejecución no duplica (SC-005).

### Rutas (`routes/web.php`) — ver `contracts/rutas.md`

```php
Route::get('/login',  [LoginController::class,'showLogin'])->middleware('guest')->name('login');
Route::post('/login', [LoginController::class,'login'])->middleware('guest');
Route::post('/logout',[LoginController::class,'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth','activo'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('usuarios', UsuarioController::class)->middleware('can:admin.usuarios');
    Route::put('usuarios/{usuario}/password', [UsuarioController::class,'resetPassword'])
        ->middleware('can:admin.usuarios')->name('usuarios.password');
    Route::put('usuarios/{usuario}/activo', [UsuarioController::class,'toggleActivo'])
        ->middleware('can:admin.usuarios')->name('usuarios.activo');
    Route::resource('roles', RolController::class)->middleware('can:admin.roles');
    Route::get('permisos', [RolController::class,'permisos'])
        ->middleware('can:admin.roles')->name('permisos.index');
});
```

`activo` = alias de `AsegurarUsuarioActivo` (registrado en `bootstrap/app.php`).

### Vistas

- `auth/login`: formulario simple (email, password, recordarme), errores flash.
- `admin/usuarios/*`: tablas y formularios; selector múltiple de roles; botones activar/
  desactivar y resetear contraseña con confirmación.
- `admin/roles/*`: formulario con checkboxes del catálogo de permisos.
- `admin/permisos/index`: matriz permiso × rol (sólo lectura).
- Layout: menú con `@can('admin.usuarios')` / `@can('admin.roles')` y "Cerrar sesión".

### Logging (FR-018)

Canal `stack`; `Log::info('admin.usuario.creado', ['actor'=>auth()->id(), 'usuario'=>$u->id])`,
análogos para editado/activado/desactivado/rol-actualizado/permisos-actualizados.

### Pruebas (primero para las reglas)

| Prueba | Qué fija |
|---|---|
| `Auth/LoginTest` | login OK; clave mala → sin sesión; inactivo → no entra; logout; ruta protegida sin auth → redirect; no existe `/register`. |
| `Admin/UltimoAdministradorTest` | no se puede desactivar / borrar / quitar rol al único `Administrador`. |
| `Admin/PermisosDeRolTest` | usuario nuevo con rol tiene exactamente esos permisos; cambiar permisos del rol surte efecto sin re-login. |
| `Admin/UsuarioCrudTest` | CRUD completo; email único; `403` sin `admin.usuarios`; reset de contraseña funciona. |
| `Admin/RolCrudTest` | CRUD; no borrar `Administrador`; advertir/rechazar borrar rol con usuarios; `403` sin `admin.roles`; catálogo de permisos es sólo lectura. |
| `RolesPermisosSeederTest` | crea 6 permisos, 3 roles, 1 admin; idempotente. |
| (feature 001) `AutorizacionPedidosTest` | integra: `pedidos.ver` / `seguimiento.editar` / `correo.generar` / `erp.importar`. |

## Complexity Tracking

> Sin violaciones. La inspección del rol `Administrador` para la regla de integridad está
> justificada arriba y no constituye autorización por rol.
