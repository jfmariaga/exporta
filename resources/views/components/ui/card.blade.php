@props(['title' => null, 'flush' => false])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || isset($header))
        <div class="flex items-center justify-between gap-3 border-b border-ink-100 px-5 py-3.5">
            <h2 class="text-sm font-semibold text-ink-800">{{ $title }}</h2>
            @isset($header){{ $header }}@endisset
        </div>
    @endif
    <div class="{{ $flush ? '' : 'p-5' }}">
        {{ $slot }}
    </div>
</div>
