<?php

use App\Models\InventarioSincboxDetalle;
use App\Support\Permisos;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $item = '';

    #[Url]
    public string $lote = '';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    public int $porPagina = 25;

    public function updated($campo): void
    {
        if (in_array($campo, ['item', 'lote', 'desde', 'hasta', 'porPagina'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('item', 'lote', 'desde', 'hasta');
        $this->resetPage();
    }

    public function with(): array
    {
        $filas = InventarioSincboxDetalle::query()
            ->when($this->item !== '', fn ($q) => $q->where('item', 'like', "%{$this->item}%"))
            ->when($this->lote !== '', fn ($q) => $q->where('lote', 'like', "%{$this->lote}%"))
            ->when($this->desde !== '', fn ($q) => $q->whereDate('creado', '>=', $this->desde))
            ->when($this->hasta !== '', fn ($q) => $q->whereDate('creado', '<=', $this->hasta))
            ->orderByDesc('creado')
            ->orderBy('item')
            ->paginate($this->porPagina);

        return [
            'filas' => $filas,
            'sincronizadoEn' => InventarioSincboxDetalle::max('sincronizado_en'),
        ];
    }
}; ?>

<x-ui.page title="Inventario — detalle por lote"
           subtitle="Existencias de SyncBox sin totalizar: una fila por bodega, ubicación y lote.">
    <x-slot:actions>
        <a href="{{ route('inventario.index') }}" wire:navigate class="btn-secondary">Ver alertas de reparto</a>
    </x-slot:actions>

    <x-ui.card class="p-3">
        <div class="flex flex-wrap items-end gap-2">
            <div>
                <label class="lbl">Ítem</label>
                <input wire:model.live.debounce.400ms="item" placeholder="Código" class="field-sm w-32">
            </div>
            <div>
                <label class="lbl">Lote</label>
                <input wire:model.live.debounce.400ms="lote" placeholder="Lote" class="field-sm w-40">
            </div>
            <div>
                <label class="lbl">Creado desde</label>
                <input type="date" wire:model.live="desde" class="field-sm">
            </div>
            <div>
                <label class="lbl">Creado hasta</label>
                <input type="date" wire:model.live="hasta" class="field-sm">
            </div>
            <button wire:click="limpiarFiltros" class="btn-secondary">Limpiar</button>
            <div class="ms-auto text-xs text-ink-400">
                {{ $filas->total() }} fila(s)
                @if ($sincronizadoEn)
                    · sincronizado {{ \Illuminate\Support\Carbon::parse($sincronizadoEn)->format('d/m/Y H:i') }}
                @endif
            </div>
        </div>
    </x-ui.card>

    <x-ui.card flush>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Ítem</th>
                    <th>Descripción</th>
                    <th>Bodega</th>
                    <th>Ubicación</th>
                    <th>Lote</th>
                    <th class="!text-right">Cantidad</th>
                    <th>Bloqueado</th>
                    <th>Creado</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($filas as $f)
                    <tr wire:key="det-{{ $f->id }}">
                        <td class="font-semibold text-ink-900">{{ $f->item }}</td>
                        <td>{{ $f->descripcion ?? '—' }}</td>
                        <td>{{ $f->warehouse ?? '—' }}</td>
                        <td>{{ $f->ubication ?? '—' }}</td>
                        <td>{{ $f->lote ?? '—' }}</td>
                        <td class="num">{{ number_format((float) $f->cantidad, 0, ',', '.') }}</td>
                        <td>
                            @if ($f->bloqueado)
                                <x-ui.badge color="red">Sí</x-ui.badge>
                            @else
                                <x-ui.badge color="gray">No</x-ui.badge>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">{{ $f->creado?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="!py-8 text-center text-ink-400">Sin filas para ese filtro.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <div>{{ $filas->links() }}</div>
</x-ui.page>
