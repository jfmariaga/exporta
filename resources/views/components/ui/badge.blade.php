@props(['color' => 'gray'])

@php
    $colors = [
        'gray' => 'bg-ink-100 text-ink-700 ring-ink-200',
        'green' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'red' => 'bg-red-50 text-red-700 ring-red-200',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-200',
        'indigo' => 'bg-brand-50 text-brand-700 ring-brand-200',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.($colors[$color] ?? $colors['gray'])]) }}>
    {{ $slot }}
</span>
