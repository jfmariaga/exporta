@props(['title' => null, 'flush' => false])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || isset($header))
        <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5 dark:border-white/10">
            <h2 class="text-sm font-semibold text-ink-900 dark:text-gray-100">{{ $title }}</h2>
            @isset($header){{ $header }}@endisset
        </div>
    @endif
    <div class="{{ $flush ? '' : 'p-5' }}">
        {{ $slot }}
    </div>
</div>
