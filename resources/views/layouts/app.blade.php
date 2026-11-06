<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">

    <script>
        (function () {
            var stored = localStorage.getItem('theme');
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (!stored && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-canvas dark:bg-canvas-dark">
<div x-data="{ mobileOpen: false }" x-on:keydown.escape.window="mobileOpen = false" class="min-h-screen lg:flex">

    {{-- Fondo oscuro (móvil) --}}
    <div x-show="mobileOpen" x-transition.opacity x-cloak
         class="fixed inset-0 z-30 bg-black/60 lg:hidden" @click="mobileOpen = false"></div>

    {{-- Barra lateral: negra, fija en pantallas grandes, colapsable --}}
    <aside :class="[mobileOpen ? 'translate-x-0' : '-translate-x-full', $store.sidebar.collapsed ? 'lg:w-20' : 'lg:w-64']"
           class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-black
                  transition-all duration-200
                  lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:self-start">
        <div class="flex h-16 flex-none items-center gap-2.5 bg-white px-4">
            <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-white p-1">
                <img src="{{ asset('img/isotipo-panal.png') }}" alt="Panal" class="h-full w-full rounded object-cover">
            </span>
            <div class="min-w-0 leading-tight" x-show="!$store.sidebar.collapsed || mobileOpen">
                <div class="truncate text-sm font-bold text-ink-900">Seguimiento de Pedidos</div>
                <div class="truncate text-[11px] text-ink-secondary">Exportaciones</div>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto py-4 px-3 text-sm">
            @can('pedidos.ver')
                <a href="{{ route('pedidos.index') }}" wire:navigate
                   :title="$store.sidebar.collapsed && !mobileOpen ? 'Pedidos por liberar' : null"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('pedidos.*') ? 'bg-brand-600 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-4 w-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6M9 16h6M9 8h6M5 4h14v16H5z"/></svg>
                    <span x-show="!$store.sidebar.collapsed || mobileOpen">Pedidos por liberar</span>
                </a>
            @endcan
            @can('pedidos.ver')
                <a href="{{ route('materiales.index') }}" wire:navigate
                   :title="$store.sidebar.collapsed && !mobileOpen ? 'Gestión por ítem' : null"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('materiales.*') ? 'bg-brand-600 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-4 w-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                    <span x-show="!$store.sidebar.collapsed || mobileOpen">Gestión por ítem</span>
                </a>
            @endcan
            @can('correo.generar')
                <a href="{{ route('cargues.correo') }}" wire:navigate
                   :title="$store.sidebar.collapsed && !mobileOpen ? 'Correo de cargues' : null"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('cargues.*') ? 'bg-brand-600 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-4 w-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="m22 6-10 7L2 6"/></svg>
                    <span x-show="!$store.sidebar.collapsed || mobileOpen">Correo de cargues</span>
                </a>
            @endcan
            @can('seguimiento.editar')
                <a href="{{ route('inventario.index') }}" wire:navigate
                   :title="$store.sidebar.collapsed && !mobileOpen ? 'Inventario' : null"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('inventario.*') ? 'bg-brand-600 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-4 w-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7l-8-4-8 4m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <span x-show="!$store.sidebar.collapsed || mobileOpen">Inventario</span>
                </a>
            @endcan

            @canany(['admin.usuarios', 'admin.roles'])
                <div class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wide text-gray-500" x-show="!$store.sidebar.collapsed || mobileOpen">Administración</div>
            @endcanany
            @can('admin.usuarios')
                <a href="{{ route('admin.usuarios.index') }}" wire:navigate
                   :title="$store.sidebar.collapsed && !mobileOpen ? 'Usuarios' : null"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('admin.usuarios.*') ? 'bg-brand-600 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-4 w-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span x-show="!$store.sidebar.collapsed || mobileOpen">Usuarios</span>
                </a>
                <a href="{{ route('admin.clientes.index') }}" wire:navigate
                   :title="$store.sidebar.collapsed && !mobileOpen ? 'Clientes' : null"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('admin.clientes.*') ? 'bg-brand-600 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-4 w-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9h.01M9 13h.01M9 17h.01"/></svg>
                    <span x-show="!$store.sidebar.collapsed || mobileOpen">Clientes</span>
                </a>
                <a href="{{ route('admin.parametros-item.index') }}" wire:navigate
                   :title="$store.sidebar.collapsed && !mobileOpen ? 'Parámetros de ítem' : null"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('admin.parametros-item.*') ? 'bg-brand-600 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-4 w-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.27 6.96 8.73 5.05 8.73-5.05M12 22.08V12"/></svg>
                    <span x-show="!$store.sidebar.collapsed || mobileOpen">Parámetros de ítem</span>
                </a>
            @endcan
            @can('admin.roles')
                <a href="{{ route('admin.roles.index') }}" wire:navigate
                   :title="$store.sidebar.collapsed && !mobileOpen ? 'Roles' : null"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('admin.roles.*') || request()->routeIs('admin.permisos.*') ? 'bg-brand-600 text-white' : 'text-gray-300 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-4 w-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span x-show="!$store.sidebar.collapsed || mobileOpen">Roles</span>
                </a>
            @endcan
        </nav>

        <div class="flex-none border-t border-white/10 p-3">
            <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                <div class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-white/10 text-sm font-semibold text-white">
                    {{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                </div>
                <div class="min-w-0 flex-1 leading-tight" x-show="!$store.sidebar.collapsed || mobileOpen">
                    <div class="truncate text-sm font-medium text-white">{{ auth()->user()->name }}</div>
                    <div class="truncate text-[11px] text-gray-400">{{ auth()->user()->email }}</div>
                </div>
            </div>
            <div class="mt-1 flex gap-1" x-show="!$store.sidebar.collapsed || mobileOpen">
                <a href="{{ route('profile') }}" wire:navigate class="flex-1 rounded-lg px-3 py-1.5 text-center text-xs font-semibold text-gray-300 hover:bg-white/10 hover:text-white">Perfil</a>
                <form method="POST" action="{{ route('logout') }}" class="flex-1">
                    @csrf
                    <button class="w-full rounded-lg px-3 py-1.5 text-xs font-semibold text-danger-dark hover:bg-white/10">Salir</button>
                </form>
            </div>
            <button type="button" x-on:click="$store.sidebar.toggle()" class="mt-1 hidden w-full items-center justify-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium text-gray-400 hover:bg-white/10 hover:text-white lg:flex">
                <svg class="h-4 w-4 transition-transform" :class="$store.sidebar.collapsed ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                <span x-show="!$store.sidebar.collapsed">Colapsar menú</span>
            </button>
        </div>
    </aside>

    {{-- Contenido --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 border-b border-line bg-surface/80 backdrop-blur dark:border-white/10 dark:bg-surface-dark/80">
            <div class="flex items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <button @click="mobileOpen = !mobileOpen" class="-ml-2 inline-flex items-center justify-center rounded-lg p-2 text-ink-secondary hover:bg-surface-alt dark:text-gray-400 dark:hover:bg-white/5 lg:hidden">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="min-w-0 flex-1">
                    @isset($header)
                        {{ $header }}
                    @else
                        <h1 class="text-lg font-semibold text-ink-900 dark:text-gray-100">{{ config('app.name') }}</h1>
                    @endisset
                </div>
                <x-theme-toggle />
            </div>
        </header>

        <main class="flex-1">
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
