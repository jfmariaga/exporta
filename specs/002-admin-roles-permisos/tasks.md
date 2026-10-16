---
description: "Task list — Administración de Roles y Permisos"
---

# Tasks: Administración de Roles y Permisos

**Input**: `specs/002-admin-roles-permisos/` (`spec.md`, `plan.md`, `data-model.md`, `contracts/rutas.md`)

**Tests**: incluidos (Principio IV — las reglas de seguridad llevan prueba primero).

**Organización**: por historia de usuario. US1–US4 son todas P1 (esta feature es prerrequisito de la 001).

## Formato: `[ID] [P?] [Story] Descripción`

---

## Phase 1: Setup

- [ ] T001 `composer require spatie/laravel-permission`; `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`.
- [ ] T002 [P] `.env` / `.env.example`: agregar `ADMIN_EMAIL`, `ADMIN_PASSWORD`.
- [ ] T003 [P] `app/Support/Permisos.php` con las 6 constantes + `Permisos::TODOS`; (opcional) `app/Support/RolesBase.php`.

---

## Phase 2: Foundational (bloqueante)

- [ ] T004 Migración `add_activo_to_users_table` (`activo` bool default true + index; opcional `password_cambiada_en`).
- [ ] T005 [P] Migración: añadir `protegido` bool a `roles` (o documentar lista blanca en código).
- [ ] T006 [P] `User`: agregar trait `HasRoles`, `activo` a `$fillable`/`$casts`, scope `activos()`.
- [ ] T007 `app/Http/Middleware/AsegurarUsuarioActivo.php` + registrar alias `activo` en `bootstrap/app.php`.
- [ ] T008 [P] `app/Support/AdministradorGuard.php`: `garantizarNoEsUltimo(User $u)` y `esRolProtegido(string $name)`.
- [ ] T009 Ejecutar `php artisan migrate` y confirmar tablas de spatie + `users.activo`.

---

## Phase 3: User Story 4 — Catálogo de permisos y roles sembrados (Priority: P1) 🎯 arranque

**Objetivo**: `RolesPermisosSeeder` idempotente crea permisos, roles base y admin inicial.

### Tests (primero)

- [ ] T010 [P] [US4] `tests/Feature/RolesPermisosSeederTest.php`: tras seed hay 6 permisos, roles `Administrador`/`Planeador`/`Consulta` con sus permisos exactos, y 1 usuario admin con rol `Administrador`; re-ejecutar no duplica.

### Implementación

- [ ] T011 [US4] `database/seeders/RolesPermisosSeeder.php` (usa `Permission::findOrCreate`, `Role::findOrCreate`, `syncPermissions`, `User::firstOrCreate` + `syncRoles`; marca `Administrador` como `protegido`).
- [ ] T012 [US4] Registrar `RolesPermisosSeeder` en `DatabaseSeeder`.

**Checkpoint**: base usable; se puede iniciar sesión como admin.

---

## Phase 4: User Story 1 — Iniciar y cerrar sesión (Priority: P1)

### Tests (primero)

- [ ] T013 [P] [US1] `tests/Feature/Auth/LoginTest.php`: login OK → redirige a `pedidos.index`; clave incorrecta → sin sesión + error genérico; usuario inactivo → no entra; `logout` termina sesión; ruta protegida sin auth → redirige a `/login`; `GET /register` → `404`.

### Implementación

- [ ] T014 [US1] `app/Http/Requests/Auth/LoginRequest.php`.
- [ ] T015 [US1] `app/Http/Controllers/Auth/LoginController.php` (`showLogin`, `login` con verificación de `activo` + `Auth::attempt` + `session()->regenerate()`, `logout`).
- [ ] T016 [US1] Rutas `GET/POST /login` (`guest`), `POST /logout` (`auth`) en `routes/web.php`.
- [ ] T017 [P] [US1] Vista `resources/views/auth/login.blade.php` (email, password, recordarme, errores flash) + layout base `resources/views/layouts/app.blade.php` con "Cerrar sesión".
- [ ] T018 [US1] Aplicar middleware `['auth','activo']` al grupo de rutas de negocio; `RedirectIfAuthenticated`/`Authenticate` apuntan a `login` / `pedidos.index`.

**Checkpoint**: la app queda cerrada tras login; inactivos fuera.

---

## Phase 5: User Story 3 — Administrar roles y permisos (Priority: P1)

### Tests (primero)

- [ ] T019 [P] [US3] `tests/Feature/Admin/PermisosDeRolTest.php`: usuario nuevo con rol → permisos exactos del rol; cambiar permisos del rol surte efecto en la siguiente request **sin re-login** (`forgetCachedPermissions`).
- [ ] T020 [P] [US3] `tests/Feature/Admin/RolCrudTest.php`: CRUD; no borrar `Administrador`; rechazar/─advertir borrar rol con usuarios; `403` sin `admin.roles`; `/admin/permisos` es sólo lectura.

### Implementación

- [ ] T021 [US3] `app/Http/Requests/Admin/GuardarRolRequest.php` (`name` único, `permisos.*` in catálogo).
- [ ] T022 [US3] `app/Http/Controllers/Admin/RolController.php` (`index`, `create`, `store`, `edit`, `update` con `syncPermissions` + `forgetCachedPermissions`, `destroy` con guardas, `permisos`).
- [ ] T023 [US3] Rutas `Route::resource('roles')` + `GET /admin/permisos` bajo `prefix('admin')->middleware(['auth','activo','can:admin.roles'])`.
- [ ] T024 [P] [US3] Vistas `admin/roles/{index,create,edit}.blade.php` (checkboxes del catálogo) y `admin/permisos/index.blade.php` (matriz permiso × rol, sólo lectura).

**Checkpoint**: se pueden definir y ajustar roles.

---

## Phase 6: User Story 2 — Administrar usuarios (Priority: P1)

### Tests (primero)

- [ ] T025 [P] [US2] `tests/Feature/Admin/UsuarioCrudTest.php`: crear/editar/listar; email único; asignar rol; reset de contraseña; `403` sin `admin.usuarios`.
- [ ] T026 [P] [US2] `tests/Feature/Admin/UltimoAdministradorTest.php`: no desactivar / borrar / quitar rol al único `Administrador`; con dos admins sí se permite.

### Implementación

- [ ] T027 [US2] `app/Http/Requests/Admin/GuardarUsuarioRequest.php` (reglas de `data-model.md`).
- [ ] T028 [US2] `app/Http/Controllers/Admin/UsuarioController.php` (`index` con filtros, `create`, `store` con `syncRoles`, `edit`, `update` con password opcional, `destroy`, `resetPassword`, `toggleActivo`; todas las guardas vía `AdministradorGuard`).
- [ ] T029 [US2] Rutas `Route::resource('usuarios')` + `PUT usuarios/{usuario}/password` + `PUT usuarios/{usuario}/activo` bajo `middleware('can:admin.usuarios')`.
- [ ] T030 [P] [US2] Vistas `admin/usuarios/{index,create,edit}.blade.php` (selector múltiple de roles, botones activar/desactivar y resetear contraseña con confirmación).

**Checkpoint**: administración de accesos completa.

---

## Phase 7: Polish & Cross-Cutting

- [ ] T031 [P] Logging de eventos de administración (`admin.usuario.creado`, `admin.rol.permisos_actualizados`, …) con `actor` + timestamp (FR-018).
- [ ] T032 [P] Navegación: entradas `Usuarios` / `Roles` con `@can`; mostrar nombre y rol del usuario logueado.
- [ ] T033 [P] `Authenticate::redirectTo` → `route('login')`; página `403` y `404` con layout propio.
- [ ] T034 (Opcional) Forzar cambio de contraseña provisional si `password_cambiada_en` es `null`.
- [ ] T035 `vendor/bin/pint`; `php artisan test` completo en verde.
- [ ] T036 [P] Actualizar `CLAUDE.md` (rutas `/admin`, permisos, seeder, cómo crear usuarios).

---

## Dependencias y orden

- Phase 1 → Phase 2 → Phase 3 (seeder) antes de poder entrar.
- US1 (login) antes de US2/US3 (necesitan sesión y `can:`).
- US3 (roles) conviene antes de US2 (la UI de usuarios asigna roles).
- `AdministradorGuard` (T008) es prerrequisito de US2.
- Esta feature completa **desbloquea** `AutorizacionPedidosTest` y todos los `can:` de la feature 001.

### Paralelizables

- T002–T003; T005–T006–T008; los tests `[P]` de cada historia; las vistas `[P]` (T017, T024, T030).

## Estrategia de MVP

1. Phase 1–2.
2. Phase 3 → seeder (hay admin).
3. Phase 4 → **login funcional**. Parar y validar.
4. Phase 5 → roles.
5. Phase 6 → usuarios. **Módulo de administración completo.**
6. Phase 7 pulido.
