@props(['campo', 'actual' => '', 'dir' => 'asc', 'align' => 'left'])

<th class="{{ $align === 'right' ? '!text-right' : '' }}">
    <button type="button" wire:click="ordenarPor('{{ $campo }}')"
            class="inline-flex items-center gap-1 whitespace-nowrap hover:text-ink-900 dark:hover:text-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded {{ $align === 'right' ? 'flex-row-reverse' : '' }}">
        <span>{{ $slot }}</span>
        @if ($actual === $campo && $dir === 'desc')
            <svg class="h-3 w-3 flex-none text-brand-600 dark:text-brand-300" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .75.75v10.638l3.96-4.158a.75.75 0 1 1 1.08 1.04l-5.25 5.5a.75.75 0 0 1-1.08 0l-5.25-5.5a.75.75 0 1 1 1.08-1.04l3.96 4.158V3.75A.75.75 0 0 1 10 3Z" clip-rule="evenodd"/>
            </svg>
        @else
            <svg class="h-3 w-3 flex-none {{ $actual === $campo ? 'text-brand-600 dark:text-brand-300' : 'text-ink-300 dark:text-gray-600' }}" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.75-.75V5.612L5.29 9.77a.75.75 0 0 1-1.08-1.04l5.25-5.5a.75.75 0 0 1 1.08 0l5.25 5.5a.75.75 0 1 1-1.08 1.04l-3.96-4.158V16.25A.75.75 0 0 1 10 17Z" clip-rule="evenodd"/>
            </svg>
        @endif
    </button>
</th>
