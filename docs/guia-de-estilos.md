# Guía de estilos — Consulta de Aprobaciones LOGGRO

Esta guía documenta, tal como está implementado hoy en el código (no es una propuesta ni
una idealización), el sistema visual de esta aplicación: paleta, tipografía, componentes
Blade, layouts y el correo de recordatorio. El objetivo es que cualquier persona o agente
pueda reproducir el mismo diseño en otra aplicación, sea Laravel o no.

Todos los valores de esta guía están extraídos literalmente de:

- `tailwind.config.js`
- `resources/css/app.css`
- `resources/js/app.js`
- `resources/views/components/*.blade.php`
- `resources/views/layouts/app.blade.php` y `layouts/guest.blade.php`
- `resources/views/livewire/**/*.blade.php` (pantallas reales de aprobaciones y reportes)
- `resources/views/emails/approval-reminder.blade.php`
- `package.json` / `composer.json`
- Los valores **por defecto de Tailwind CSS v3.4** (confirmados contra el paquete
  instalado en `node_modules/tailwindcss`), para todo lo que el proyecto usa sin
  sobreescribir en `tailwind.config.js` (espaciados, radios, sombras, breakpoints,
  tamaños de letra, la escala de grises, negro/blanco).

Los tokens también están disponibles en formato JSON en
[`docs/design-tokens.json`](./design-tokens.json), para importarlos en otras
herramientas (Figma Tokens, Style Dictionary, un `tailwind.config` o CSS vars de otra
app, etc.).

> **Nota sobre componentes no usados**: `resources/views/components/nav-link.blade.php`
> y `responsive-nav-link.blade.php` son plantillas por defecto de Laravel Breeze que
> **nadie referencia** en la aplicación (`grep` de `x-nav-link`/`x-responsive-nav-link`
> no encuentra usos) y todavía tienen colores índigo/gris genéricos de Breeze, no la
> paleta de marca. No forman parte del sistema de diseño real y **no deben
> reproducirse** — se documentan aquí solo para que no se confundan con componentes
> vigentes.
>
> **Nota sobre pantallas de error**: la aplicación no tiene vistas de error 404/500/419
> personalizadas (no existe `resources/views/errors/`); usa las páginas por defecto de
> Laravel para esos casos. Lo que sí tiene diseño propio es la comunicación de errores
> **dentro del flujo** (ERP no disponible, documento no encontrado, formulario
> inválido), que se resuelve con el componente `<x-alert>` — documentado en la sección
> 2 como "pantalla de error" funcional de esta app.

---

## 1. Tokens

### 1.1 Paleta de color

Toda la paleta de marca vive en `tailwind.config.js` → `theme.extend.colors`. Es la
identidad Levapan (rojo/vino), compartida con la otra app hermana (ActivosFijos).

#### Marca (`brand`) — rojo/vino Levapan

| Token | Hex | Uso real en la app |
|---|---|---|
| `brand-50` | `#FBEAEA` | Fondo del badge `color="brand"` |
| `brand-100` | `#F6D4D4` | — |
| `brand-200` | `#EDAAAA` | — |
| `brand-300` | `#E27E7E` | Texto del badge `color="brand"` en modo oscuro |
| `brand-400` | `#DA5E5E` | — |
| `brand-500` | `#E24545` | Anillo de foco (`focus:ring-brand-500`) en botones, inputs, enlaces |
| `brand-600` | `#CF1D1D` | **Color de marca principal**: botón primario, enlace activo del menú lateral, barra de progreso, texto de enlaces de acción |
| `brand-700` | `#A81616` | Hover/focus del botón primario, texto del badge `color="brand"` en modo claro |
| `brand-800` | `#8E1212` | Active del botón primario |
| `brand-900` | `#6E0E0E` | Círculo decorativo difuminado del panel de login |
| `brand-950` | `#4A0909` | — |

#### Texto (`ink`)

| Token | Hex | Uso |
|---|---|---|
| `ink` (DEFAULT) | `#111111` | Texto principal en modo claro |
| `ink-secondary` | `#616161` | Texto secundario/metadatos (etiquetas, fechas, ayudas) |
| `ink-muted` | `#3C3C3C` | Texto atenuado (poco usado directamente; la mayoría de variantes "mudas" se resuelven con `ink-secondary` o `gray-400` en oscuro) |

#### Fondos (`canvas`, `surface`)

| Token | Hex | Uso |
|---|---|---|
| `canvas` (DEFAULT) | `#FFFFFF` | Fondo de `<body>` en modo claro |
| `canvas-dark` | `#121212` | Fondo de `<body>` en modo oscuro |
| `surface` (DEFAULT) | `#FFFFFF` | Fondo de tarjetas, topbar, inputs |
| `surface-alt` | `#F7F4F4` | Fondo "hover"/alterno de superficies claras, encabezado de la tarjeta de resultado |
| `surface-dark` | `#1C1C1C` | Fondo de tarjetas/topbar en modo oscuro |

#### Bordes (`line`)

| Token | Hex | Uso |
|---|---|---|
| `line` (DEFAULT) | `#EDE8EC` | Todo borde/separador en modo claro (tarjetas, tablas, inputs, `<hr>` visuales) |

En modo oscuro **no** se usa una variante de `line`: los bordes pasan a
`border-white/10` (blanco al 10% de opacidad) directamente en cada clase, p. ej.
`border-line dark:border-white/10`.

#### Estados semánticos

| Token | Hex | Uso |
|---|---|---|
| `success` (DEFAULT) | `#1A7A42` | Texto de badge/alert de éxito |
| `success-soft` | `#E6F4EC` | Fondo de badge/alert de éxito |
| `success-dark` | `#2FAE68` | Texto de éxito en modo oscuro |
| `warning` (DEFAULT) | `#E58A45` | Borde de alert de advertencia (al 30% opacidad), fondo de marcador "pendiente" en la línea de tiempo |
| `warning-soft` | `#FDF0E6` | Fondo de badge/alert de advertencia, caja de "documento modificado" |
| `warning-text` | `#9C5319` | Texto de badge/alert de advertencia (más oscuro que `warning` para contraste) |
| `danger` (DEFAULT) | `#7A1E1E` | Texto de badge/alert de peligro, fondo de botón de peligro |
| `danger-soft` | `#F6E9E9` | Fondo de badge/alert de peligro |
| `danger-dark` | `#D96E6E` | Texto de peligro en modo oscuro |
| `danger-hover` | `#5E1717` | Hover del botón de peligro |
| `danger-active` | `#431010` | Active del botón de peligro |
| `accent` (DEFAULT) | `#E58A45` | Igual que `warning` (acento naranja secundario) |
| `accent-soft` | `#FFD7A7` | — |
| `cold` (DEFAULT) | `#D5DFF7` | Fondo del alert tipo `info` (por defecto) |

#### Neutros sin personalizar (por defecto de Tailwind v3)

Estos **no** están en `tailwind.config.js`; son los valores que trae Tailwind y que la
app usa tal cual (confirmados contra `node_modules/tailwindcss`):

- `black` = `#000000` → fondo del menú lateral (`bg-black`), overlay móvil
  (`bg-black/60`), panel de marca del login.
- `white` = `#FFFFFF`.
- Escala `gray` (usada en variantes `dark:` y en componentes sin recolorear, como el
  modal y el dropdown):

| Token | Hex |
|---|---|
| `gray-50` | `#f9fafb` |
| `gray-100` | `#f3f4f6` |
| `gray-200` | `#e5e7eb` |
| `gray-300` | `#d1d5db` |
| `gray-400` | `#9ca3af` |
| `gray-500` | `#6b7280` |
| `gray-600` | `#4b5563` |
| `gray-700` | `#374151` |
| `gray-800` | `#1f2937` |
| `gray-900` | `#111827` |
| `gray-950` | `#030712` |

### 1.2 Tipografía

- **Familia**: Poppins, pesos **400, 500, 600 y 700**.
- **Carga**: web font externa desde Bunny Fonts (alternativa a Google Fonts sin
  cookies), en `<head>` de ambos layouts:
  ```html
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700&display=swap" rel="stylesheet" />
  ```
- **Configuración Tailwind** (`tailwind.config.js`):
  ```js
  import defaultTheme from 'tailwindcss/defaultTheme';
  // ...
  fontFamily: {
      sans: ['Poppins', ...defaultTheme.fontFamily.sans],
  },
  ```
  Es decir, el stack final es `Poppins, ui-sans-serif, system-ui, sans-serif,
  "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"`.
- **Aplicación**: `<body class="font-sans antialiased ...">` en ambos layouts.
- **Tamaños/pesos usados** (escala por defecto de Tailwind, no personalizada):
  - `text-xs` (12px/16px), `text-sm` (14px/20px), `text-base` (16px/24px),
    `text-lg` (18px/28px) — body, metadatos, títulos de tarjeta.
  - `font-medium` (500) para etiquetas y énfasis leve; `font-semibold` (600) para
    títulos, botones y valores destacados.

### 1.3 Espaciado, radios y sombras

No hay personalización en `tailwind.config.js` más allá de color y tipografía: **todo
el espaciado, los radios de borde y las sombras son los valores por defecto de
Tailwind CSS v3.4**. El subconjunto realmente usado en la interfaz:

- **Espaciado** (para `p-*`, `gap-*`, `space-y-*`, `px-*`, `py-*`): `1` = 0.25rem,
  `1.5` = 0.375rem, `2` = 0.5rem, `3` = 0.75rem, `4` = 1rem, `5` = 1.25rem,
  `6` = 1.5rem, `8` = 2rem, `9` = 2.25rem, `10` = 2.5rem, `12` = 3rem, `16` = 4rem,
  `20` = 5rem (ancho del menú lateral colapsado), `64` = 16rem (ancho del menú lateral
  expandido).
- **Radios de borde**: `rounded-md` (6px, botones de ícono), `rounded-lg` (8px,
  botones, inputs, cajas), `rounded-3xl` (24px, tarjetas `<x-card>` y tarjeta de
  login/registro), `rounded-full` (badges, barra de progreso, marcador numerado de la
  línea de tiempo, avatar).
- **Sombras**: `shadow-sm` (tarjetas, botones), `shadow-lg` (panel del dropdown),
  `shadow-xl` (modal).

### 1.4 Breakpoints

Por defecto de Tailwind, sin personalizar: `sm` 640px, `md` 768px, `lg` 1024px,
`xl` 1280px, `2xl` 1536px. El quiebre de layout relevante (menú lateral fijo vs.
off-canvas) ocurre en **`lg` (1024px)**.

### 1.5 Modo oscuro

- **Estrategia**: `darkMode: 'class'` — se activa agregando la clase `dark` a `<html>`.
- **Persistencia**: `localStorage.theme` (`'dark'` / `'light'`); si no hay valor
  guardado, se respeta `prefers-color-scheme: dark` del sistema.
- **Anti-parpadeo**: ambos layouts incluyen este script inline en `<head>`, **antes**
  de `@vite`, para aplicar la clase `dark` antes del primer pintado:
  ```html
  <script>
      (function () {
          var stored = localStorage.getItem('theme');
          var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
          if (stored === 'dark' || (!stored && prefersDark)) {
              document.documentElement.classList.add('dark');
          }
      })();
  </script>
  ```
- **Toggle**: componente `<x-theme-toggle>` (botón con ícono sol/luna) que alterna la
  clase y guarda la preferencia (ver sección 2.5).

---

## 2. Catálogo de componentes

Todos los componentes son Blade nativos (`resources/views/components/*.blade.php`),
sin librería de componentes externa. Reciben clases adicionales vía
`$attributes->merge([...])`, así que cualquier consumidor puede añadir clases propias
sin perder las base.

### 2.1 Botón primario — `<x-primary-button>`

```html
<button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-brand-600 border border-transparent rounded-lg font-semibold text-sm text-white hover:bg-brand-700 focus:bg-brand-700 active:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-surface-dark transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed">
    Buscar
</button>
```

- **Prop opcional**: `arrow` (bool) — agrega un ícono `heroicon-o-arrow-right` después
  del texto.
- **Estados**: hover `brand-700`, focus `brand-700` + anillo `brand-500` (offset 2px,
  color de offset `surface-dark` en modo oscuro para que el anillo no se "coma" el
  fondo), active `brand-800`, disabled `opacity-50` + `cursor-not-allowed`.
- **Accesibilidad**: el anillo de foco (`focus:ring-2`) es visible por teclado;
  `disabled:cursor-not-allowed` da feedback visual sin necesidad de `aria-disabled`
  porque usa el atributo `disabled` nativo del `<button>`.

### 2.2 Botón secundario — `<x-secondary-button>`

```html
<button type="button" class="inline-flex items-center justify-center px-4 py-2 bg-surface dark:bg-surface-dark border border-line dark:border-white/10 rounded-lg font-semibold text-sm text-ink dark:text-gray-200 shadow-sm hover:bg-surface-alt dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-surface-dark disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150">
    Cancelar
</button>
```

- Fondo blanco/`surface-dark` con borde `line`, texto `ink`. El anillo de foco usa el
  mismo `brand-500` del primario (coherencia de marca aunque el botón sea neutro).

### 2.3 Botón de peligro — `<x-danger-button>`

```html
<button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-danger border border-transparent rounded-lg font-semibold text-sm text-white hover:bg-danger-hover active:bg-danger-active focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2 dark:focus:ring-offset-surface-dark transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed">
    Eliminar
</button>
```

- Único botón cuyo anillo de foco es del propio color semántico (`ring-danger`) en vez
  de `brand-500`, para reforzar que es una acción destructiva.

### 2.4 Inputs y etiquetas

**Etiqueta** — `<x-input-label :value="'Correo electrónico'">`:
```html
<label class="block font-medium text-sm text-ink dark:text-gray-300">Correo electrónico</label>
```

**Input de texto** — `<x-text-input type="email" />`:
```html
<input class="border-line dark:border-white/10 dark:bg-surface-dark dark:text-gray-200 focus:border-brand-500 dark:focus:border-brand-500 focus:ring-brand-500 dark:focus:ring-brand-500 rounded-lg shadow-sm" />
```
El plugin `@tailwindcss/forms` provee la base de reseteo (bordes, padding, line-height
consistentes entre navegadores); estas clases solo recolorean esa base.

**Input de contraseña** — `<x-password-input>`: igual que el de texto, con botón de
mostrar/ocultar superpuesto (ícono `heroicon-o-eye` / `heroicon-o-eye-slash`,
alternado con `x-show`/Alpine) y `pr-10` para no tapar el texto con el botón:
```html
<div class="relative">
    <input :type="show ? 'text' : 'password'" class="border-line ... rounded-lg shadow-sm pr-10">
    <button type="button" class="absolute inset-y-0 right-0 flex items-center px-3 text-ink-secondary dark:text-gray-400 hover:text-ink dark:hover:text-gray-200">
        <!-- heroicon-o-eye / heroicon-o-eye-slash -->
    </button>
</div>
```
- **Accesibilidad**: el botón de mostrar/ocultar tiene `:aria-label` dinámico
  ("Mostrar contraseña" / "Ocultar contraseña").

**Error de validación** — `<x-input-error :messages="$errors->get('campo')" />`:
```html
<ul class="text-sm text-danger dark:text-danger-dark space-y-1">
    <li>El campo es obligatorio.</li>
</ul>
```
Solo se renderiza si hay mensajes (`@if ($messages)`); no ocupa espacio cuando no hay
error.

### 2.5 Toggle de tema — `<x-theme-toggle>`

```html
<button type="button" class="inline-flex items-center justify-center h-9 w-9 rounded-md text-ink-secondary hover:bg-surface-alt dark:text-gray-400 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
    <span class="sr-only">Cambiar tema</span>
    <!-- heroicon-o-sun: visible solo en modo oscuro (hidden dark:block) -->
    <!-- heroicon-o-moon: visible solo en modo claro (block dark:hidden) -->
</button>
```
Al hacer clic alterna `document.documentElement.classList.toggle('dark')` y guarda la
preferencia en `localStorage.theme`. **Accesibilidad**: texto `sr-only` para lectores
de pantalla, ya que el botón solo muestra un ícono.

### 2.6 Tarjeta — `<x-card>`

```html
<div class="bg-surface dark:bg-surface-dark border border-line dark:border-white/10 rounded-3xl shadow-sm p-6">
    <!-- contenido -->
</div>
```
- **Prop**: `padding` (bool, default `true`) — con `false` quita el `p-6` para que el
  propio contenido controle el padding (así lo usa la tarjeta de resultado de
  aprobación, que necesita una cabecera con fondo distinto pegada al borde superior).

### 2.7 Badge de estado — `<x-badge color="...">`

```html
<span class="inline-flex items-center rounded-full px-3.5 py-1.5 text-sm sm:text-base font-semibold {color-classes}">
    Pendiente
</span>
```

No hay variantes de **tamaño** en el componente (un solo tamaño, que crece de
`text-sm` a `text-base` en `sm:` y más); la única prop es `color`:

| `color` | Clases | Uso real |
|---|---|---|
| `success` | `bg-success-soft text-success dark:bg-success-dark/20 dark:text-success-dark` | Aprobado / completado |
| `warning` | `bg-warning-soft text-warning-text dark:bg-warning/20 dark:text-warning` | Pendiente |
| `danger` | `bg-danger-soft text-danger dark:bg-danger-dark/20 dark:text-danger-dark` | Rechazado |
| `brand` | `bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300` | Énfasis de marca (poco usado) |
| *(default, incl. `"gray"`)* | `bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300` | Estados neutros: "Por liberar", "Cerrado", "Sin aprobar · documento cerrado" |

### 2.8 Alertas — `<x-alert type="...">`

```html
<div class="flex items-start gap-3 rounded-lg border p-4 text-sm {classes}">
    <!-- heroicon-o-{icono} class="h-5 w-5 shrink-0" -->
    <div>Mensaje</div>
</div>
```

| `type` | Clases | Ícono | Uso real |
|---|---|---|---|
| `info` *(default)* | `bg-cold text-ink border-line dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700` | `o-information-circle` | — |
| `success` | `bg-success-soft text-success border-success/20 dark:bg-success-dark/10 dark:text-success-dark dark:border-success-dark/20` | `o-check-circle` | — |
| `warning` | `bg-warning-soft text-warning-text border-warning/30 dark:bg-warning/10 dark:text-warning dark:border-warning/30` | `o-exclamation-triangle` | "No se encontró ninguna Requisición/Orden con ese consecutivo", "No se encontró trazabilidad de aprobación" |
| `danger` | `bg-danger-soft text-danger border-danger/20 dark:bg-danger-dark/10 dark:text-danger-dark dark:border-danger-dark/20` | `o-x-circle` | "El ERP no está disponible en este momento" |

Esta es, en la práctica, **la pantalla de error de la aplicación**: no hay una vista
404/500 propia; los estados de error/vacío se comunican in-line con `<x-alert>` dentro
del formulario de búsqueda (`type="danger"` para fallas del ERP, `type="warning"` para
"no encontrado" o datos faltantes).

### 2.9 Modal — `<x-modal name="..." :show="false" maxWidth="2xl">`

Overlay (`bg-gray-500 dark:bg-gray-900 opacity-75`) + panel centrado:
```html
<div class="mb-6 bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:max-w-2xl sm:mx-auto">
    <!-- contenido -->
</div>
```
- **Comportamiento**: controlado por Alpine (`x-data`/`x-show`), se abre/cierra vía
  eventos de navegador `open-modal`/`close-modal` con el `name` como payload.
  `maxWidth` acepta `sm|md|lg|xl|2xl`.
- **Accesibilidad**: trampa de foco manual (recorre elementos enfocables con Tab/
  Shift+Tab dentro del modal), cierre con `Escape`, y bloquea el scroll del body
  mientras está abierto (`overflow-y-hidden`).
- Nota: este es el único componente que usa `gray-500`/`gray-800`/`gray-900` "crudos"
  de Tailwind en vez de los tokens `surface`/`ink`; no se recoloreó al adoptar la
  paleta de marca.

### 2.10 Tabla (patrón, no componente reutilizable)

No existe un `<x-table>`; el patrón real (de
`resources/views/livewire/reports/pending-approvals-table.blade.php`) es una tabla
HTML nativa con Tailwind:

```html
<table class="min-w-full divide-y divide-line dark:divide-white/10 text-sm">
    <thead>
        <tr class="text-left text-xs font-medium uppercase tracking-wide text-ink-secondary dark:text-gray-400">
            <th scope="col" class="py-2 pr-4">
                <button class="hover:text-ink dark:hover:text-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded">
                    Consecutivo
                </button>
            </th>
            <!-- ... -->
        </tr>
    </thead>
    <tbody class="divide-y divide-line dark:divide-white/10">
        <tr>
            <td class="py-2 pr-4">
                <a class="font-medium text-brand-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded">146</a>
            </td>
            <!-- ... -->
        </tr>
    </tbody>
</table>
```
- **Encabezados ordenables**: `<button>` dentro del `<th>`, con `aria-sort`
  (`"ascending"` / `"descending"` / `"none"`) en el `<th>` mismo.
- **Overflow responsive**: la tabla va envuelta en `<div class="overflow-x-auto">`.
- **Paginación**: botones "Anterior"/"Siguiente" con la clase del botón secundario y
  `disabled` nativo en los extremos.
- **Accesibilidad**: `scope="col"` en encabezados, foco visible en encabezados
  ordenables y enlaces (`focus-visible:ring-2 focus-visible:ring-brand-500`).

### 2.11 Menú lateral — `<x-sidebar>` + `<x-sidebar-link>`

```html
<aside class="fixed inset-y-0 left-0 z-50 flex flex-col bg-black transition-all duration-200 lg:translate-x-0 w-64"
       :class="[mobileOpen ? 'translate-x-0' : '-translate-x-full', $store.sidebar.collapsed ? 'lg:w-20' : 'lg:w-64']">
    <div class="flex items-center gap-3 min-h-16 px-4 py-3 bg-white shrink-0">
        <!-- logo + nombre de la app -->
    </div>
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        <!-- x-sidebar-link por cada entrada de menú -->
    </nav>
    <div class="p-3 border-t border-white/10 hidden lg:block">
        <!-- botón colapsar/expandir -->
    </div>
</aside>
```

**Enlace de menú** — `<x-sidebar-link :href :active :icon="'home'">`:
```html
<a class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ $active ? 'bg-brand-600 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
    <!-- heroicon-o-{icon} h-5 w-5 -->
    <span x-show="!$store.sidebar.collapsed || mobileOpen">Texto del enlace</span>
</a>
```
- Activo: fondo `brand-600`, texto blanco. Inactivo: `gray-300`, hover `white/10` +
  texto blanco.
- Cuando el menú está colapsado en escritorio, el texto se oculta (`x-show`) y el
  enlace expone un `:title` con el texto, para que siga siendo identificable al pasar
  el mouse.
- **Overlay móvil**: `<div class="fixed inset-0 z-40 bg-black/60 lg:hidden" x-show="mobileOpen">` detrás del menú, que lo cierra al hacer clic.

### 2.12 Barra superior — `<x-topbar>`

```html
<header class="sticky top-0 z-30 h-16 flex items-center gap-4 bg-surface dark:bg-surface-dark border-b border-line dark:border-white/10 px-4 sm:px-6">
    <button class="lg:hidden -ml-1 inline-flex items-center justify-center h-9 w-9 rounded-md text-ink-secondary hover:bg-surface-alt dark:text-gray-400 dark:hover:bg-gray-700">
        <span class="sr-only">Abrir menú</span>
        <!-- heroicon-o-bars-3, solo visible < lg -->
    </button>
    <div class="flex-1"></div>
    <x-theme-toggle />
    <x-dropdown><!-- menú de usuario: Perfil / Cerrar sesión --></x-dropdown>
</header>
```
Sticky en la parte superior (`sticky top-0 z-30`), altura fija `h-16`.

### 2.13 Línea de tiempo de aprobaciones (patrón, no componente reutilizable)

El componente más específico del dominio, en
`resources/views/livewire/partials/document-result.blade.php`. No es un
`<x-timeline>` reutilizable; es markup directo en la vista de resultado:

```html
<ol class="space-y-5">
    <li class="relative pl-10 pb-5 border-l-2 border-line dark:border-white/10 ml-4">
        <span class="absolute -left-4 top-0 flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold {marker-color}">
            1
        </span>
        <div class="pl-2">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium text-ink dark:text-gray-100">Jorge Tobon</span>
                <x-badge color="warning">Pendiente</x-badge>
                <x-badge color="danger">Excede 3 días hábiles</x-badge> <!-- condicional -->
            </div>
            <div class="text-sm text-ink-secondary dark:text-gray-400 mt-1">
                En cola desde 02/11/2026 07:00 · 26.0 h (en curso) · 0 de 1 líneas aprobadas
            </div>
            <!-- botón "Enviar recordatorio" condicional -->
            <button class="mt-2 text-xs font-medium text-brand-600 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded">
                Ver líneas
            </button>
            <!-- panel colapsable con el detalle de líneas -->
        </div>
    </li>
    <!-- el último <li> no lleva border-l-2 (se corta la línea conectora) -->
</ol>
```

- **Marcador numerado** (`$groupMarkerColors`, todas con texto blanco):
  `bg-success` aprobado, `bg-danger` con rechazos, `bg-warning` pendiente/parcial,
  `bg-gray-400` cuando el documento está cerrado y ese paso nunca se aprobó ("Sin
  aprobar · documento cerrado").
- **Conector vertical**: `border-l-2 border-line` en cada `<li>` salvo el último,
  simulando la línea continua de un stepper.
- **Detalle de líneas** (colapsable con Alpine `x-show`/`x-cloak`): cada línea muestra
  `Ítem {código} · {descripción, recortada a 2 líneas con line-clamp-2 y title con el
  texto completo} · Línea {n}` a la izquierda, y el estado + fecha a la derecha.
- **Barra de progreso** del documento completo:
  ```html
  <div class="h-2 w-full rounded-full bg-line dark:bg-white/10 overflow-hidden" role="progressbar" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100" aria-label="Avance de aprobadores">
      <div class="h-full rounded-full bg-brand-600 transition-all" style="width: 50%"></div>
  </div>
  ```
  **Accesibilidad**: `role="progressbar"` con `aria-valuenow/min/max` y
  `aria-label` descriptivo.
- **Accesibilidad general del patrón**: cada sección colapsable ("Ver líneas",
  "Historial de rondas anteriores") usa `:aria-expanded` sincronizado con el estado de
  Alpine y `aria-controls` apuntando al `id` del panel que controla.

---

## 3. Layouts

### 3.1 Página autenticada — `layouts/app.blade.php`

Estructura: menú lateral fijo a la izquierda + barra superior sticky + contenido,
usando un único store de Alpine (`$store.sidebar`, registrado en `resources/js/app.js`)
para coordinar el estado colapsado/expandido entre el `<aside>` y el padding del
contenido:

```html
<body class="font-sans antialiased bg-canvas dark:bg-canvas-dark">
    <div class="transition-all duration-200" :class="$store.sidebar.collapsed ? 'lg:pl-20' : 'lg:pl-64'" x-data>
        <livewire:layout.navigation /> <!-- <x-sidebar/> + <x-topbar/> -->

        @if (isset($header))
            <header class="bg-surface dark:bg-surface-dark border-b border-line dark:border-white/10">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">{{ $header }}</div>
            </header>
        @endif

        <main>{{ $slot }}</main>
    </div>
</body>
```
- El padding izquierdo del contenido (`lg:pl-64` / `lg:pl-20`) **debe coincidir
  exactamente** con el ancho del `<aside>` (`w-64`/`lg:w-20`) para que el contenido no
  quede debajo del menú en escritorio.
- Por debajo de `lg`, el menú es off-canvas (`-translate-x-full` por defecto) y no
  reserva espacio (sin `pl-*`), controlado por `mobileOpen` en
  `livewire/layout/navigation.blade.php` (`x-data="{ mobileOpen: false }"`, con
  `x-on:keydown.escape.window="mobileOpen = false"`).
- Las pantallas de contenido usan contenedores propios, normalmente
  `<div class="py-12"><div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">...`.

### 3.2 Páginas de invitado (login/registro/etc.) — `layouts/guest.blade.php`

Grid de 2 columnas en escritorio (`lg:grid lg:grid-cols-2`); un panel de marca a la
izquierda y el formulario a la derecha. En móvil, una sola columna con un header propio:

```html
<body class="font-sans antialiased bg-canvas dark:bg-canvas-dark" x-data>
    <div class="min-h-screen lg:grid lg:grid-cols-2">
        <!-- Panel de marca, solo lg+ -->
        <div class="hidden lg:flex relative flex-col justify-between bg-black text-white p-12 overflow-hidden">
            <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-brand-700/40 blur-3xl"></div>
            <div class="absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-brand-900/40 blur-3xl"></div>
            <!-- logo, titular, copyright -->
        </div>

        <!-- Formulario -->
        <div class="flex flex-col justify-center px-4 py-12 sm:px-6 lg:px-12">
            <div class="flex items-center justify-between mb-8 lg:hidden"><!-- header móvil: logo + theme-toggle --></div>
            <div class="hidden lg:flex justify-end mb-6"><x-theme-toggle /></div>
            <div class="w-full max-w-sm mx-auto lg:mx-0 bg-surface dark:bg-surface-dark border border-line dark:border-white/10 rounded-3xl shadow-sm p-8">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
```
- El panel de marca usa dos círculos difuminados (`blur-3xl`) en `brand-700/40` y
  `brand-900/40` como único elemento decorativo — nada de imágenes ni ilustraciones.
- La tarjeta del formulario es `rounded-3xl` + `shadow-sm`, igual receta que
  `<x-card>` pero con `p-8` en vez de `p-6` y `max-w-sm`.

### 3.3 Comportamiento responsive/móvil (resumen transversal)

- **Quiebre único**: `lg` (1024px) decide menú fijo vs. off-canvas, y 1 vs. 2 columnas
  en las pantallas de invitado.
- **Menú lateral**: off-canvas con overlay oscuro bajo `lg`; colapsable (ancho
  reducido, sin textos) solo en escritorio, con estado persistido en
  `localStorage.sidebarCollapsed`.
- **Formularios de búsqueda**: `flex flex-col sm:flex-row` (apilados en móvil, en
  línea desde `sm`, 640px).
- **Tablas**: `overflow-x-auto` como única estrategia responsive (sin vista de tarjetas
  alterna en móvil).
- **Descripciones largas** en la línea de tiempo: `line-clamp-2` + `break-words`, para
  no romper el layout en pantallas angostas.

---

## 4. Dependencias y build

### 4.1 Frontend (`package.json`)

```json
{
  "devDependencies": {
    "@tailwindcss/forms": "^0.5.2",
    "autoprefixer": "^10.4.2",
    "laravel-vite-plugin": "^3.1",
    "postcss": "^8.4.31",
    "tailwindcss": "^3.1.0",
    "vite": "^8.0.0"
  }
}
```
Versión realmente instalada de Tailwind: **3.4.19** (vía `npm ls tailwindcss`). El
paquete `@tailwindcss/vite` aparece en el árbol de dependencias (transitivo) pero
**no se usa**: `vite.config.js` no lo registra como plugin; el proyecto compila Tailwind
a la manera clásica v3, con PostCSS.

**`postcss.config.js`**:
```js
export default {
    plugins: {
        tailwindcss: {},
        autoprefixer: {},
    },
};
```

**`resources/css/app.css`** (completo, 3 líneas):
```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

**`vite.config.js`**:
```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
```

**`resources/js/app.js`** (completo): registra el store de Alpine del menú lateral.
Alpine.js en sí **no** es una dependencia npm del proyecto: llega empaquetado con
Livewire 3 (que lo inyecta automáticamente en la página).
```js
document.addEventListener('alpine:init', () => {
    Alpine.store('sidebar', {
        collapsed: localStorage.getItem('sidebarCollapsed') === 'true',
        toggle() {
            this.collapsed = !this.collapsed;
            localStorage.setItem('sidebarCollapsed', this.collapsed);
        },
    });
});
```

**Build**: `npm run build` (→ `vite build`) genera `public/build/` con manifest; en
desarrollo, `npm run dev` levanta el servidor de Vite con HMR.

### 4.2 Backend relevante al diseño (`composer.json`)

- `livewire/livewire` `^3.6.4` — componentes reactivos; trae Alpine.js embebido.
- `livewire/volt` `^1.7.0` — sintaxis de un solo archivo usada en las páginas de auth.
- `blade-ui-kit/blade-heroicons` `^2.7` — el set de íconos (estilo *outline*, prefijo
  `heroicon-o-*`), usado en sidebar, topbar, alertas, botones de mostrar/ocultar
  contraseña, etc.

---

## 5. Cómo llevar el diseño a otra aplicación

### 5.1 A otra app Laravel

1. **Copiar tal cual**:
   - `tailwind.config.js` (toda la sección `theme.extend.colors` y `fontFamily`).
   - `resources/css/app.css`.
   - `postcss.config.js`.
   - `resources/js/app.js` (el store `sidebar`; si la otra app no tiene menú lateral
     colapsable, se puede omitir).
   - Los componentes de `resources/views/components/`: `primary-button`,
     `secondary-button`, `danger-button`, `text-input`, `password-input`,
     `input-label`, `input-error`, `card`, `badge`, `alert`, `modal`, `dropdown`,
     `dropdown-link`, `sidebar`, `sidebar-link`, `topbar`, `theme-toggle`,
     `application-logo`, `auth-session-status`, `action-message`. **No** copiar
     `nav-link`/`responsive-nav-link` (sin usar, colores Breeze sin migrar).
   - `resources/views/layouts/app.blade.php` y `guest.blade.php` (ajustando las rutas
     `route('home')`, `route('profile')`, etc. a las de la otra app).
2. **Instalar dependencias**:
   ```sh
   npm install -D tailwindcss@^3.1.0 @tailwindcss/forms autoprefixer postcss laravel-vite-plugin vite
   composer require blade-ui-kit/blade-heroicons livewire/livewire
   ```
3. **Agregar el `<link>` de Poppins** (Bunny Fonts) en el `<head>` de los layouts.
4. **Agregar el script anti-parpadeo** de modo oscuro antes de `@vite` en cada layout.
5. Adaptar el menú lateral (`<x-sidebar>`) con las entradas de navegación propias de la
   otra app, manteniendo las clases de color (`bg-black`, `bg-brand-600` activo,
   `text-gray-300` inactivo).
6. `npm run build` y `vendor/bin/pint` (si se adoptan también las convenciones PHP).

### 5.2 A una app que **no** sea Laravel/Blade

No hay forma de "copiar" los componentes Blade; hay que reconstruirlos con los
tokens. Pasos:

1. **Tokens**: importar `docs/design-tokens.json` o traducir `color`, `typography`,
   `spacing`, `borderRadius`, `boxShadow`, `breakpoints` a lo que use el stack destino
   (CSS custom properties, un tema de Tailwind/UnoCSS, variables de SCSS, el theme de
   un design system tipo MUI/Chakra, etc.). Si el stack destino no usa Tailwind,
   traducir cada clase de esta guía a CSS equivalente usando los valores de la tabla
   de la sección 1 (p. ej. `bg-brand-600` → `background-color: #CF1D1D`).
2. **Tipografía**: cargar Poppins 400/500/600/700 (desde Bunny Fonts, Google Fonts, o
   auto-hospedada) con el mismo stack de *fallback* (`ui-sans-serif, system-ui,
   sans-serif, ...`).
3. **Modo oscuro**: reimplementar la lógica de `localStorage.theme` +
   `prefers-color-scheme` + script anti-parpadeo en `<head>`, y la clase `dark` (o el
   mecanismo equivalente del framework CSS destino) sobre el elemento raíz.
4. **Componentes**: reconstruir cada uno de la sección 2 con el HTML/clases dados ahí,
   traduciendo las utilidades de Tailwind a CSS del framework destino componente por
   componente (botones, inputs, tarjeta, badge, alertas, tabla, modal, menú
   lateral/topbar, línea de tiempo). Mantener los mismos estados (hover/focus/active/
   disabled) y las mismas notas de accesibilidad (`aria-expanded`, `aria-controls`,
   `role="progressbar"`, `sr-only`, `:aria-label` dinámico, trampa de foco del modal).
5. **Interactividad**: Alpine.js puede reutilizarse vía CDN sin Livewire (es una
   librería standalone de ~15kb); si el stack destino ya tiene su propio manejo de
   estado (React/Vue/Svelte), reimplementar en él la lógica descrita (toggle de menú
   colapsado/off-canvas, toggle de tema, acordeones de la línea de tiempo, dropdown de
   usuario, trampa de foco del modal).
6. **Iconografía**: usar el set Heroicons *outline* (es un set open-source disponible
   como SVG sueltos, paquete npm `heroicons`, o vía CDN) para mantener exactamente los
   mismos íconos que usa `blade-heroicons`.

---

## 6. Correo de recordatorio de aprobación

`resources/views/emails/approval-reminder.blade.php` (Mailable
`App\Mail\ApprovalReminderMail`) es un **caso aparte**: los clientes de correo
(Outlook, Gmail, etc.) no soportan Tailwind ni `<link>`/`<style>` externos de forma
fiable, así que todo está resuelto con **estilos en línea**, tablas `role="presentation"`
para el layout, y los **mismos valores hex** de la paleta de la sección 1 (no colores
inventados para el correo).

- **Ancho máximo**: `600px`, diseño de una columna, centrado con una tabla contenedora
  `width="100%"` + `align="center"`.
- **Tipografía**: `Arial, Helvetica, sans-serif` (no Poppins — los web fonts no son
  fiables en clientes de correo).
- **Fondo de página**: `#F7F4F4` (mismo que `surface-alt`).
- **Tarjeta**: fondo `#FFFFFF`, borde `#EDE8EC` (`line`), radio `12px 12px 0 0` /
  `0 0 12px 12px` (header/cuerpo).
- **Encabezado**: fondo `#000000` (igual que el menú lateral), texto blanco,
  `font-weight: 600`, `font-size: 18px`.
- **Texto principal**: `#111111` (`ink`); **texto secundario**: `#616161`
  (`ink-secondary`).
- **Caja de "documento modificado"**: fondo `#FDF0E6` (`warning.soft`), texto `#111111`,
  radio `8px` — misma receta que `<x-alert type="warning">` pero sin el borde (no es
  viable en todos los clientes de correo mantener `border` + `border-radius` + fondo
  con buena consistencia).
- **Botón "Ir a LOGGRO"**: fondo `#CF1D1D` (`brand-600`), texto blanco, `font-weight:
  600`, radio `8px`, construido con una tabla anidada (patrón "bulletproof button" de
  email, compatible con Outlook, que no soporta `border-radius` en `<a>` directamente):
  ```html
  <table role="presentation" cellpadding="0" cellspacing="0">
      <tr>
          <td style="background-color: #CF1D1D; border-radius: 8px;">
              <a href="{{ $loggroUrl }}" target="_blank" style="display: inline-block; padding: 12px 28px; font-size: 15px; font-weight: 600; color: #FFFFFF; text-decoration: none; border-radius: 8px; font-family: Arial, Helvetica, sans-serif;">
                  Ir a LOGGRO
              </a>
          </td>
      </tr>
  </table>
  ```
  Debajo del botón, la misma URL en texto plano (`#616161`, `12px`,
  `word-break: break-all`) para clientes que bloquean botones/enlaces con estilo.
- **Separadores**: `border-top`/`border-bottom: 1px solid #EDE8EC` (mismo `line`) entre
  secciones (datos del documento, pie de página).
- **URL configurable**: `config('reminders.loggro_url')` (env `LOGGRO_URL`, por
  defecto `https://panal.loggro.com/`) — nunca escrita fija en la vista.

Vista previa rápida (sin enviar correo real):
```sh
php artisan tinker --execute="
\$mail = new App\Mail\ApprovalReminderMail(
    documentTypeLabel: 'Orden de Compra',
    sequential: '72078',
    requestedByName: 'Victor Soto',
    documentCreatedAt: \Carbon\CarbonImmutable::now(),
    waitingTimeLabel: '26.0 h',
    senderName: 'Jorge Tobon',
);
file_put_contents('storage/app/preview.html', \$mail->render());
"
```
Y abrir `storage/app/preview.html` en el navegador.
