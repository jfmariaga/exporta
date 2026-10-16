# Feature Specification: Administración de Roles y Permisos

**Feature Branch**: `002-admin-roles-permisos`

**Created**: 2026-08-28

**Status**: Implementado

> **Enmienda 2026-08-28.** La autenticación la provee **Laravel Breeze (stack Livewire + Volt)**;
> se elimina el registro público y la recuperación de clave por correo (los usuarios los crea el
> administrador). El chequeo de usuario `activo` se hace en `App\Livewire\Forms\LoginForm`. El
> CRUD de usuarios y roles son **componentes Livewire (Volt)** con paginación, no controladores.

**Input**: Módulo de administración para autenticación y gestión de **usuarios**, **roles** y
**permisos** de la aplicación de Seguimiento de Pedidos. Autenticación mínima propia (login /
logout, sin auto-registro; los usuarios los crea un administrador) sobre
`spatie/laravel-permission`. Las rutas de negocio se protegen por **permiso nombrado**, no por
rol.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Iniciar y cerrar sesión (Priority: P1)

Como usuario del área de exportaciones, quiero iniciar sesión con mi correo y contraseña y
cerrar sesión, para usar la aplicación de forma segura.

**Why this priority**: Sin autenticación no hay forma de proteger nada; bloquea todo el resto.

**Independent Test**: Crear un usuario activo por seeder/factory, iniciar sesión con
credenciales válidas e inválidas, y cerrar sesión.

**Acceptance Scenarios**:

1. **Given** un usuario activo, **When** ingreso correo y contraseña correctos,
   **Then** quedo autenticado y soy redirigido a la lista de pedidos por liberar.
2. **Given** un usuario activo, **When** ingreso una contraseña incorrecta,
   **Then** vuelvo al login con un error genérico y sin sesión.
3. **Given** un usuario **inactivo**, **When** ingreso credenciales correctas,
   **Then** no se me autentica y se muestra "usuario inactivo".
4. **Given** una sesión iniciada, **When** hago clic en "Cerrar sesión",
   **Then** la sesión termina y vuelvo al login.
5. **Given** que no estoy autenticado, **When** abro cualquier ruta protegida,
   **Then** soy redirigido al login.
6. **Given** la aplicación, **When** busco una ruta de registro público,
   **Then** no existe (los usuarios sólo los crea un administrador).

---

### User Story 2 - Administrar usuarios (Priority: P1)

Como administrador (`admin.usuarios`), quiero crear, listar, editar, activar/desactivar
usuarios, asignarles roles y resetear su contraseña, para controlar quién accede y con qué
alcance.

**Why this priority**: Es el núcleo del módulo de administración solicitado.

**Independent Test**: Con un usuario con `admin.usuarios`, recorrer el CRUD completo y verificar
que un usuario nuevo con rol "Planeador" obtiene exactamente los permisos de ese rol.

**Acceptance Scenarios**:

1. **Given** permiso `admin.usuarios`, **When** creo un usuario con nombre, correo, contraseña
   y rol "Planeador", **Then** el usuario queda activo y con los permisos del rol.
2. **Given** un usuario existente, **When** lo edito y le cambio el rol a "Consulta",
   **Then** sus permisos efectivos pasan a ser los de "Consulta".
3. **Given** un usuario existente, **When** lo desactivo,
   **Then** no puede iniciar sesión, pero su historial de seguimiento se conserva.
4. **Given** un usuario, **When** uso "resetear contraseña" y defino una nueva,
   **Then** el usuario puede entrar con la nueva contraseña.
5. **Given** que soy el único usuario con rol "Administrador", **When** intento quitarme ese
   rol o desactivarme, **Then** la acción se rechaza ("debe existir al menos un administrador").
6. **Given** que **no** tengo `admin.usuarios`, **When** abro `/admin/usuarios`,
   **Then** recibo `403`.
7. **Given** un correo ya registrado, **When** intento crear otro usuario con ese correo,
   **Then** la validación lo rechaza.

---

### User Story 3 - Administrar roles y sus permisos (Priority: P1)

Como administrador (`admin.roles`), quiero crear, listar, editar y eliminar roles, y asignarles
permisos del catálogo, para agrupar accesos de forma mantenible.

**Why this priority**: Los roles son la unidad de administración de accesos; requerido para US2.

**Independent Test**: Crear un rol, asignarle un subconjunto de permisos, asignárselo a un
usuario y verificar los permisos efectivos; intentar eliminar un rol en uso.

**Acceptance Scenarios**:

1. **Given** permiso `admin.roles`, **When** creo el rol "Coordinador" con
   `pedidos.ver` + `correo.generar`, **Then** el rol queda disponible para asignar.
2. **Given** el rol "Planeador", **When** le agrego `erp.importar` y guardo,
   **Then** los usuarios con ese rol pasan a poder importar.
3. **Given** un rol asignado a 1+ usuarios, **When** intento eliminarlo,
   **Then** la acción se rechaza o pide reasignar; no quedan usuarios sin rol de forma silenciosa.
4. **Given** el rol "Administrador", **When** intento eliminarlo,
   **Then** la acción se rechaza (rol protegido).
5. **Given** la pantalla de permisos, **When** la abro,
   **Then** veo el catálogo fijo de permisos (sólo lectura) y qué roles tiene cada uno.
6. **Given** que **no** tengo `admin.roles`, **When** abro `/admin/roles`,
   **Then** recibo `403`.

---

### User Story 4 - Catálogo de permisos y roles sembrados (Priority: P1)

Como responsable del despliegue, quiero que la instalación siembre el catálogo de permisos, los
roles base y un usuario administrador inicial, para que el sistema sea usable desde el arranque.

**Why this priority**: Sin datos base no se puede entrar ni administrar nada.

**Independent Test**: Ejecutar `php artisan db:seed --class=RolesPermisosSeeder` sobre una base
limpia y verificar permisos, roles y el usuario admin; re-ejecutar sin duplicar.

**Acceptance Scenarios**:

1. **Given** una base limpia, **When** ejecuto el seeder, **Then** existen los permisos
   `pedidos.ver`, `seguimiento.editar`, `correo.generar`, `erp.importar`, `admin.usuarios`,
   `admin.roles`.
2. **Given** el seeder, **When** lo ejecuto, **Then** existen los roles `Administrador`
   (todos los permisos), `Planeador` (`pedidos.ver`, `seguimiento.editar`, `correo.generar`) y
   `Consulta` (`pedidos.ver`).
3. **Given** el seeder, **When** lo ejecuto, **Then** existe un usuario administrador inicial
   con rol `Administrador` (correo y contraseña provisional definidos por variables de entorno).
4. **Given** que ya ejecuté el seeder, **When** lo ejecuto otra vez,
   **Then** no se crean duplicados (idempotente).

---

### Edge Cases

- **Último administrador**: no se puede desactivar, borrar, ni quitarle el rol `Administrador`
  al único usuario que lo tiene.
- **Auto-bloqueo**: un administrador no puede quitarse a sí mismo `admin.usuarios` si con eso
  queda el sistema sin nadie que administre usuarios.
- **Rol sin permisos**: permitido (rol "vacío"); los usuarios con él sólo pueden ver el login y
  su propio perfil.
- **Usuario sin rol**: permitido técnicamente, pero la UI de creación exige al menos uno; un
  usuario sin permisos recibe `403` en toda ruta de negocio.
- **Cambio de permisos en caliente**: al cambiar los permisos de un rol, los usuarios con ese
  rol ven el efecto en su siguiente petición (sin necesidad de re-login).
- **Contraseña**: mínimo 8 caracteres; se almacena con hash (bcrypt/argon2 por defecto de
  Laravel); nunca se muestra ni se registra en logs.
- **Sesión**: expira según `config/session.php`; "recordarme" opcional.
- **Correo**: único, case-insensitive.
- **Eliminación de usuario**: se prefiere **desactivar**; si se borra, los registros de
  seguimiento que lo referencian conservan el `actualizado_por` (FK con `nullOnDelete` o
  usuario "eliminado").

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: El sistema DEBE permitir iniciar sesión con correo + contraseña y cerrar sesión.
- **FR-002**: El sistema NO DEBE ofrecer registro público de usuarios.
- **FR-003**: El sistema DEBE impedir el inicio de sesión de usuarios marcados como inactivos.
- **FR-004**: El sistema DEBE redirigir al login toda petición no autenticada a rutas
  protegidas.
- **FR-005**: El sistema DEBE usar `spatie/laravel-permission` para roles y permisos, con el
  guard `web`.
- **FR-006**: El sistema DEBE proteger cada ruta de negocio con un **permiso nombrado**
  (`can:<permiso>`), nunca comprobando el nombre del rol en el código.
- **FR-007**: Los usuarios con `admin.usuarios` DEBEN poder listar, crear, editar,
  activar/desactivar usuarios, asignarles uno o más roles y resetear su contraseña.
- **FR-008**: Los usuarios con `admin.roles` DEBEN poder listar, crear, editar y eliminar
  roles, y asignarles permisos del catálogo.
- **FR-009**: El catálogo de permisos DEBE ser fijo y sembrado; la UI lo muestra en **sólo
  lectura** (no se crean/borran permisos desde la aplicación).
- **FR-010**: El sistema DEBE impedir eliminar/desactivar/despojar de rol al **único** usuario
  con rol `Administrador`.
- **FR-011**: El sistema DEBE impedir eliminar los roles protegidos (`Administrador`) y advertir
  al eliminar un rol asignado a usuarios.
- **FR-012**: El sistema DEBE validar que el correo de usuario sea único (case-insensitive) y
  la contraseña tenga al menos 8 caracteres.
- **FR-013**: El sistema DEBE almacenar contraseñas con hash y nunca exponerlas.
- **FR-014**: Un cambio en los permisos de un rol DEBE reflejarse en los usuarios de ese rol en
  su siguiente petición, sin re-login.
- **FR-015**: El sistema DEBE proveer un seeder idempotente (`RolesPermisosSeeder`) que cree
  permisos, roles base (`Administrador`, `Planeador`, `Consulta`) y un usuario administrador
  inicial parametrizado por variables de entorno.
- **FR-016**: El sistema DEBE registrar `actualizado_por` en los datos de seguimiento (feature
  001) usando el usuario autenticado; al borrar un usuario, esos registros no se pierden.
- **FR-017**: La navegación DEBE mostrar sólo las opciones permitidas al usuario actual
  (`@can`).
- **FR-018**: El sistema DEBE registrar (log) los eventos de creación/edición/activación de
  usuarios y cambios de roles/permisos, con el actor y la marca de tiempo.

### Catálogo de permisos (fijo)

| Permiso | Protege |
|---|---|
| `pedidos.ver` | Ver lista y detalle de pedidos por liberar |
| `seguimiento.editar` | Editar estado de materiales / observaciones / fecha tentativa |
| `correo.generar` | Generar el cuerpo del correo de cargues |
| `erp.importar` | Importar el Excel del ERP |
| `admin.usuarios` | Administrar usuarios |
| `admin.roles` | Administrar roles y ver permisos |

### Roles base (sembrados)

| Rol | Permisos |
|---|---|
| `Administrador` | Todos (protegido, no eliminable) |
| `Planeador` | `pedidos.ver`, `seguimiento.editar`, `correo.generar` |
| `Consulta` | `pedidos.ver` |

### Key Entities *(include if feature involves data)*

- **Usuario** (`users`): `name`, `email` (único), `password` (hash), `activo` (bool),
  `remember_token`, timestamps. Relación N:N con roles (vía spatie).
- **Rol** (`roles` de spatie): `name`, `guard_name`. Marca `protegido` (para `Administrador`).
  N:N con permisos.
- **Permiso** (`permissions` de spatie): `name`, `guard_name`. Catálogo fijo.
- **Tablas pivote de spatie**: `model_has_roles`, `model_has_permissions`, `role_has_permissions`.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: El 100% de las rutas de negocio (feature 001) responde `403` a usuarios sin el
  permiso correspondiente y `200`/`302` a quienes lo tienen.
- **SC-002**: Un usuario recién creado con un rol tiene **exactamente** los permisos de ese rol
  (ni más ni menos), verificado por prueba.
- **SC-003**: Nunca es posible dejar el sistema sin ningún usuario con rol `Administrador`
  activo (todas las rutas que lo intentarían fallan).
- **SC-004**: Un usuario inactivo no logra iniciar sesión en el 100% de los intentos.
- **SC-005**: Re-ejecutar `RolesPermisosSeeder` no cambia el número de permisos, roles ni
  usuarios.
- **SC-006**: Cambiar los permisos de un rol surte efecto para sus usuarios sin que ellos
  cierren y reabran sesión (verificado por prueba de integración).
- **SC-007**: Ninguna contraseña aparece en texto plano en respuestas, vistas ni logs.

## Assumptions

- La app es de uso interno (área de exportaciones de Levapan); pocas decenas de usuarios.
- No se requiere SSO, verificación de correo, 2FA ni "olvidé mi contraseña" por correo en v1:
  el reseteo de contraseña lo hace un administrador.
- El guard es `web` (sesión). No hay API tokens en v1.
- Un solo idioma (español) y un huso horario (America/Bogota).
- El usuario administrador inicial se define con `ADMIN_EMAIL` y `ADMIN_PASSWORD` en `.env`; la
  contraseña provisional se cambia tras el primer ingreso.
- Esta feature se implementa **antes** o **junto** con la 001, que depende de su catálogo de
  permisos.

## Dependencies

- `spatie/laravel-permission` (`composer require spatie/laravel-permission`).
- Base de datos MySQL configurada.
- Feature `001-seguimiento-pedidos-liberar` consume los permisos definidos aquí.
