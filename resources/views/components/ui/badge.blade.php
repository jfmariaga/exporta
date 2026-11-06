@props(['color' => 'gray'])

@php
    $colors = [
        'gray' => 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:ring-white/10',
        'green' => 'bg-success-soft text-success ring-success/20 dark:bg-success-dark/20 dark:text-success-dark dark:ring-success-dark/20',
        'amber' => 'bg-warning-soft text-warning-text ring-warning/30 dark:bg-warning/20 dark:text-warning dark:ring-warning/30',
        'red' => 'bg-danger-soft text-danger ring-danger/20 dark:bg-danger-dark/20 dark:text-danger-dark dark:ring-danger-dark/20',
        'blue' => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-900/30 dark:text-sky-300 dark:ring-sky-800/40',
        'indigo' => 'bg-brand-50 text-brand-700 ring-brand-200 dark:bg-brand-900/40 dark:text-brand-300 dark:ring-brand-800/40',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.($colors[$color] ?? $colors['gray'])]) }}>
    {{ $slot }}
</span>
