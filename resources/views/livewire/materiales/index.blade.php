<?php

use App\Enums\EstadoMateriales;
use App\Services\PedidosPorLiberarQuery;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $estado = '';

    public int $porPagina = 25;

    public function updatedEstado(): void
    {
        $this->resetPage();
    }

    public function with(PedidosPorLiberarQuery $consulta): array
    {
        return [
            'lineas' => $this->estado !== ''
                ? $consulta->lineasPorEstadoMateriales($this->estado, $this->porPagina)
                : null,
            'estados' => EstadoMateriales::valores(),
        ];
    }
}; ?>

<x-ui.page title="Gestión por ítem"
           subtitle="Filtra las líneas de pedidos por liberar según su estado de materiales. Los ítems sin estado asignado no se muestran.">
    <x-ui.card class="p-4">
        <label class="lbl">Estado de materiales</label>
        <select wire:model.live="estado" class="field-sm w-full sm:max-w-sm">
            <option value="">— Selecciona un estado —</option>
            @foreach ($estados as $e)
                <option value="{{ $e }}">{{ $e }}</option>
            @endforeach
        </select>
    </x-ui.card>

    @if ($lineas === null)
        <x-ui.card class="p-8 text-center text-sm text-ink-400">
            Selecciona un estado de materiales para ver los ítems que lo tienen asignado.
        </x-ui.card>
    @else
        <x-ui.card flush>
            <div class="overflow-x-auto">
                <table class="data-table data-table--compact">
                    <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Cliente</th>
                        <th>Destino</th>
                        <th>Ítem</th>
                        <th>Descripción</th>
                        <th class="!text-right">Cant. pedida</th>
                        <th>Observaciones</th>
                        <th>Fecha programada</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($lineas as $l)
                        <tr wire:key="mat-{{ $l->pedido }}-{{ $l->item }}">
                            <td>
                                <a href="{{ route('pedidos.show', $l->pedido) }}" wire:navigate class="font-semibold text-brand-600 hover:text-brand-500">{{ $l->pedido }}</a>
                            </td>
                            <td>
                                <div class="font-medium text-ink-900">{{ $l->nombre_cliente }}</div>
                                <div class="text-xs text-ink-400">{{ $l->nit_cliente }}</div>
                            </td>
                            <td class="whitespace-nowrap">{{ $l->destino }}</td>
                            <td class="font-semibold text-ink-900">{{ $l->item }}</td>
                            <td>{{ $l->descripcion }}</td>
                            <td class="num whitespace-nowrap">{{ number_format((float) $l->cantidad_pedida, 0, ',', '.') }} <span class="text-xs text-ink-400">{{ $l->unidad_medida }}</span></td>
                            <td>{{ $l->observaciones ?: '—' }}</td>
                            <td class="whitespace-nowrap">{{ $l->fecha_programa ? \Illuminate\Support\Carbon::parse($l->fecha_programa)->format('d/m/Y') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="!py-10 text-center text-ink-400">Ningún ítem tiene asignado ese estado.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <div>{{ $lineas->links() }}</div>
    @endif
</x-ui.page>
