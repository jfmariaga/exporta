<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2 bg-danger border border-transparent rounded-lg font-semibold text-sm text-white hover:bg-danger-hover active:bg-danger-active focus:outline-none focus:ring-2 focus:ring-danger focus:ring-offset-2 dark:focus:ring-offset-surface-dark transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
