<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
<div x-data="{ sidebar: false }" class="min-h-screen lg:flex">

    {{-- Fondo oscuro (móvil) --}}
    <div x-show="sidebar" x-transition.opacity x-cloak
         class="fixed inset-0 z-30 bg-ink-900/40 lg:hidden" @click="sidebar = false"></div>

    {{-- Barra lateral: fija en pantallas grandes --}}
    <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-ink-200 bg-white
                  transition-transform duration-200
                  lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:self-start">
        <div class="flex h-16 flex-none items-center gap-2.5 border-b border-ink-200 px-5">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-700 text-white">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 3H5a2 2 0 0 0-2 2v16l4-3 4 3 4-3 4 3V5a2 2 0 0 0-2-2z"/><path d="M8 7h8M8 11h8"/>
                </svg>
            </span>
            <div class="leading-tight">
                <div class="text-sm font-bold text-ink-900">Seguimiento de Pedidos</div>
                <div class="text-[11px] text-ink-400">Exportaciones</div>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto p-3 text-sm">
            @can('pedidos.ver')
                <a href="{{ route('pedidos.index') }}" wire:navigate
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('pedidos.*') ? 'bg-brand-50 text-brand-800' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900' }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6M9 16h6M9 8h6M5 4h14v16H5z"/></svg>
                    Pedidos por liberar
                </a>
            @endcan
            @can('pedidos.ver')
                <a href="{{ route('materiales.index') }}" wire:navigate
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('materiales.*') ? 'bg-brand-50 text-brand-800' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900' }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                    Gestión por ítem
                </a>
            @endcan
            @can('correo.generar')
                <a href="{{ route('cargues.correo') }}" wire:navigate
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('cargues.*') ? 'bg-brand-50 text-brand-800' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900' }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="m22 6-10 7L2 6"/></svg>
                    Correo de cargues
                </a>
            @endcan
            @can('seguimiento.editar')
                <a href="{{ route('inventario.index') }}" wire:navigate
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('inventario.*') ? 'bg-brand-50 text-brand-800' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900' }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7l-8-4-8 4m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Inventario
                </a>
            @endcan

            @canany(['admin.usuarios', 'admin.roles'])
                <div class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wide text-ink-400">Administración</div>
            @endcanany
            @can('admin.usuarios')
                <a href="{{ route('admin.usuarios.index') }}" wire:navigate
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('admin.usuarios.*') ? 'bg-brand-50 text-brand-800' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900' }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Usuarios
                </a>
                <a href="{{ route('admin.clientes.index') }}" wire:navigate
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('admin.clientes.*') ? 'bg-brand-50 text-brand-800' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900' }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9h.01M9 13h.01M9 17h.01"/></svg>
                    Clientes
                </a>
                <a href="{{ route('admin.parametros-item.index') }}" wire:navigate
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('admin.parametros-item.*') ? 'bg-brand-50 text-brand-800' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900' }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.27 6.96 8.73 5.05 8.73-5.05M12 22.08V12"/></svg>
                    Parámetros de ítem
                </a>
            @endcan
            @can('admin.roles')
                <a href="{{ route('admin.roles.index') }}" wire:navigate
                   class="flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition-colors
                          {{ request()->routeIs('admin.roles.*') || request()->routeIs('admin.permisos.*') ? 'bg-brand-50 text-brand-800' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900' }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Roles
                </a>
            @endcan
        </nav>

        <div class="flex-none border-t border-ink-200 p-3">
            <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                <div class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-ink-200 text-sm font-semibold text-ink-600">
                    {{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                </div>
                <div class="min-w-0 flex-1 leading-tight">
                    <div class="truncate text-sm font-medium text-ink-800">{{ auth()->user()->name }}</div>
                    <div class="truncate text-[11px] text-ink-400">{{ auth()->user()->email }}</div>
                </div>
            </div>
            <div class="mt-1 flex gap-1">
                <a href="{{ route('profile') }}" wire:navigate class="btn btn-secondary btn-sm flex-1 border-0 shadow-none hover:bg-ink-100">Perfil</a>
                <form method="POST" action="{{ route('logout') }}" class="flex-1">
                    @csrf
                    <button class="btn btn-secondary btn-sm w-full border-0 shadow-none text-rose-600 hover:bg-rose-50">Salir</button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Contenido --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 border-b border-ink-200 bg-white/80 backdrop-blur">
            <div class="flex items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <button @click="sidebar = !sidebar" class="btn btn-secondary -ml-2 border-0 p-2 shadow-none lg:hidden">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="min-w-0 flex-1">
                    @isset($header)
                        {{ $header }}
                    @else
                        <h1 class="text-lg font-semibold text-ink-900">{{ config('app.name') }}</h1>
                    @endisset
                </div>
            </div>
        </header>

        <main class="flex-1">
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
