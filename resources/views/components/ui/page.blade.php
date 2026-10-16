@props(['title' => null, 'subtitle' => null])

<div class="min-h-screen">
    <div class="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
        @if ($title || isset($actions))
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    @if ($title)
                        <h1 class="text-2xl font-semibold tracking-tight text-ink-900">{{ $title }}</h1>
                    @endif
                    @if ($subtitle)
                        <p class="mt-1 text-sm text-ink-500">{{ $subtitle }}</p>
                    @endif
                </div>
                @isset($actions)
                    <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                @endisset
            </div>
        @endif

        @if (session('status'))
            <div class="mb-5 flex items-start gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <div {{ $attributes->merge(['class' => 'space-y-5']) }}>
            {{ $slot }}
        </div>
    </div>
</div>
