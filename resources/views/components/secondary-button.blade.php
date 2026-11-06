<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-4 py-2 bg-surface dark:bg-surface-dark border border-line dark:border-white/10 rounded-lg font-semibold text-sm text-ink-900 dark:text-gray-200 shadow-sm hover:bg-surface-alt dark:hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-surface-dark disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
