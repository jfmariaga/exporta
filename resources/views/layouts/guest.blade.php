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
    <body class="font-sans antialiased bg-canvas dark:bg-canvas-dark" x-data>
        <div class="min-h-screen lg:grid lg:grid-cols-2">
            {{-- Panel de marca, solo escritorio --}}
            <div class="relative hidden flex-col justify-between overflow-hidden bg-black p-12 text-white lg:flex">
                <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-brand-700/40 blur-3xl"></div>
                <div class="absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-brand-900/40 blur-3xl"></div>

                <div class="relative flex items-center gap-3">
                    <span class="flex h-11 w-11 flex-none items-center justify-center rounded-lg bg-white p-1.5">
                        <img src="{{ asset('img/isotipo-panal.png') }}" alt="Panal" class="h-full w-full rounded object-cover">
                    </span>
                    <span class="text-lg font-bold">Seguimiento de Pedidos</span>
                </div>

                <div class="relative">
                    <p class="max-w-2xl text-3xl font-bold leading-snug text-white sm:text-4xl">
                        Consulta y seguimiento de pedidos de exportación por liberar.
                    </p>
                    <p class="mt-4 max-w-xl text-sm text-gray-400">
                        Toneladas, inventario y el correo de cargues, en un solo lugar.
                    </p>
                    <p class="mt-6 text-xs text-gray-500">&copy; {{ date('Y') }} Levapan. Todos los derechos reservados.</p>
                </div>
            </div>

            {{-- Formulario --}}
            <div class="flex flex-col justify-center px-4 py-12 sm:px-6 lg:px-12">
                <div class="mb-8 flex items-center justify-between lg:hidden">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-white p-1">
                            <img src="{{ asset('img/isotipo-panal.png') }}" alt="Panal" class="h-full w-full rounded object-cover">
                        </span>
                        <span class="text-base font-bold text-ink-900 dark:text-gray-100">Seguimiento de Pedidos</span>
                    </div>
                    <x-theme-toggle />
                </div>
                <div class="hidden justify-end mb-6 lg:flex">
                    <x-theme-toggle />
                </div>

                <div class="mx-auto w-full max-w-sm rounded-3xl border border-line bg-surface p-8 shadow-sm dark:border-white/10 dark:bg-surface-dark lg:mx-0">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
