@props(['title' => null, 'subtitle' => null])

<div class="min-h-screen">
    <div class="w-full px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
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
            <div x-data x-init="Toast.fire({ icon: 'success', title: @js(session('status')) })"></div>
        @endif

        <div {{ $attributes->merge(['class' => 'space-y-5']) }}>
            {{ $slot }}
        </div>
    </div>
</div>
