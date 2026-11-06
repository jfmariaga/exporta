<?php

use App\Models\InventarioSincboxDetalle;
use App\Support\ConOrdenamiento;
use App\Support\ExportaExcel;
use App\Support\Permisos;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app')] class extends Component
{
    use ConOrdenamiento, ExportaExcel, WithPagination;

    private const COLUMNAS_ORDENABLES = ['item', 'descripcion', 'warehouse', 'lote', 'cantidad', 'bloqueado', 'creado'];

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

    private function queryFilas(): Builder
    {
        // Agrupado por ítem + lote: suma la cantidad de las distintas
        // ubicaciones/bodegas de un mismo lote en una sola fila.
        $filas = InventarioSincboxDetalle::query()
            ->when($this->item !== '', fn ($q) => $q->where('item', 'like', "%{$this->item}%"))
            ->when($this->lote !== '', fn ($q) => $q->where('lote', 'like', "%{$this->lote}%"))
            ->when($this->desde !== '', fn ($q) => $q->whereDate('creado', '>=', $this->desde))
            ->when($this->hasta !== '', fn ($q) => $q->whereDate('creado', '<=', $this->hasta))
            ->groupBy('item', 'lote')
            ->selectRaw(implode(', ', [
                'item', 'lote',
                'MAX(descripcion) as descripcion',
                'MAX(warehouse) as warehouse',
                'SUM(cantidad) as cantidad',
                'MAX(bloqueado) as bloqueado',
                'MAX(creado) as creado',
            ]));

        if ($campo = $this->campoOrdenValido(self::COLUMNAS_ORDENABLES)) {
            $filas->orderBy($campo, $this->ordenDir);
        } else {
            $filas->orderByRaw('MAX(creado) desc')->orderBy('item');
        }

        return $filas;
    }

    public function exportar(): StreamedResponse
    {
        $filas = $this->queryFilas()->get();

        return $this->descargarExcel('inventario-detalle.xlsx', [
            'Ítem', 'Descripción', 'Bodega', 'Lote', 'Cantidad', 'Bloqueado', 'Creado',
        ], $filas->map(fn ($f) => [
            $f->item, $f->descripcion, $f->warehouse, $f->lote, (float) $f->cantidad,
            $f->bloqueado ? 'Sí' : 'No', $f->creado?->format('d/m/Y'),
        ]));
    }

    public function with(): array
    {
        return [
            'filas' => $this->queryFilas()->paginate($this->porPagina),
            'sincronizadoEn' => InventarioSincboxDetalle::max('sincronizado_en'),
        ];
    }
}; ?>

<x-ui.page title="Inventario — detalle por lote"
           subtitle="Existencias de SyncBox sin totalizar: una fila por bodega, ubicación y lote.">
    <x-slot:actions>
        <button wire:click="exportar" class="btn-secondary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            Exportar
        </button>
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
            <div class="ms-auto text-xs text-ink-secondary dark:text-gray-500">
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
                    <x-ui.th-sort campo="item" :actual="$ordenCampo" :dir="$ordenDir">Ítem</x-ui.th-sort>
                    <x-ui.th-sort campo="descripcion" :actual="$ordenCampo" :dir="$ordenDir">Descripción</x-ui.th-sort>
                    <x-ui.th-sort campo="warehouse" :actual="$ordenCampo" :dir="$ordenDir">Bodega</x-ui.th-sort>
                    <x-ui.th-sort campo="lote" :actual="$ordenCampo" :dir="$ordenDir">Lote</x-ui.th-sort>
                    <x-ui.th-sort campo="cantidad" :actual="$ordenCampo" :dir="$ordenDir" align="right">Cantidad</x-ui.th-sort>
                    <x-ui.th-sort campo="bloqueado" :actual="$ordenCampo" :dir="$ordenDir">Bloqueado</x-ui.th-sort>
                    <x-ui.th-sort campo="creado" :actual="$ordenCampo" :dir="$ordenDir">Creado</x-ui.th-sort>
                </tr>
                </thead>
                <tbody>
                @forelse ($filas as $f)
                    <tr wire:key="det-{{ $f->item }}-{{ $f->lote }}">
                        <td class="font-semibold text-ink-900 dark:text-gray-100">{{ $f->item }}</td>
                        <td>{{ $f->descripcion ?? '—' }}</td>
                        <td>{{ $f->warehouse ?? '—' }}</td>
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
                    <tr><td colspan="7" class="!py-8 text-center text-ink-secondary dark:text-gray-500">Sin filas para ese filtro.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <div>{{ $filas->links() }}</div>
</x-ui.page>
