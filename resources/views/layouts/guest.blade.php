<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-ink-100">
            <div class="flex items-center gap-2.5">
                <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-brand-700 text-white">
                    <x-application-logo class="h-7 w-7 fill-current" />
                </span>
                <span class="text-base font-bold text-ink-900">Seguimiento de Pedidos</span>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 card">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
