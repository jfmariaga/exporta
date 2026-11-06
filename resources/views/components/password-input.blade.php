@props(['disabled' => false])

<div class="relative" x-data="{ show: false }">
    <input
        :type="show ? 'text' : 'password'"
        @disabled($disabled)
        {{ $attributes->merge(['class' => 'border-line dark:border-white/10 dark:bg-surface-dark dark:text-gray-200 focus:border-brand-500 dark:focus:border-brand-500 focus:ring-brand-500 dark:focus:ring-brand-500 rounded-lg shadow-sm pr-10']) }}
    >
    <button
        type="button"
        x-on:click="show = !show"
        :aria-label="show ? 'Ocultar contraseña' : 'Mostrar contraseña'"
        class="absolute inset-y-0 right-0 flex items-center px-3 text-ink-secondary dark:text-gray-400 hover:text-ink-900 dark:hover:text-gray-200"
    >
        <svg x-show="!show" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/>
        </svg>
        <svg x-show="show" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
            <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 0 1-4.24-4.24"/>
            <path d="M6.61 6.61A18.5 18.5 0 0 0 1 12s4 8 11 8a9.74 9.74 0 0 0 5.39-1.61"/>
            <path d="m2 2 20 20"/>
        </svg>
    </button>
</div>
