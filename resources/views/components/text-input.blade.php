@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-line dark:border-white/10 dark:bg-surface-dark dark:text-gray-200 focus:border-brand-500 dark:focus:border-brand-500 focus:ring-brand-500 dark:focus:ring-brand-500 rounded-lg shadow-sm']) }}>
