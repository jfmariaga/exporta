<?php

use App\Enums\EstadoMateriales;
use App\Services\PedidosPorLiberarQuery;
use App\Support\ConOrdenamiento;
use App\Support\ExportaExcel;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app')] class extends Component
{
    use ConOrdenamiento, ExportaExcel, WithPagination;

    private const COLUMNAS_ORDENABLES = ['pedido', 'nombre_cliente', 'destino', 'item', 'descripcion', 'cantidad_pedida'];

    #[Url]
    public string $estado = '';

    public int $porPagina = 25;

    public function updatedEstado(): void
    {
        $this->resetPage();
    }

    public function exportar(PedidosPorLiberarQuery $consulta): ?StreamedResponse
    {
        if ($this->estado === '') {
            return null;
        }

        $filas = $consulta->lineasPorEstadoMaterialesColeccion(
            $this->estado, $this->campoOrdenValido(self::COLUMNAS_ORDENABLES), $this->ordenDir,
        );

        return $this->descargarExcel('gestion-por-item.xlsx', [
            'Pedido', 'Cliente', 'Destino', 'Ítem', 'Descripción', 'Cant. pedida',
        ], $filas->map(fn ($l) => [
            $l->pedido, $l->nombre_cliente, $l->destino, $l->item, $l->descripcion, (float) $l->cantidad_pedida,
        ]));
    }

    public function with(PedidosPorLiberarQuery $consulta): array
    {
        return [
            'lineas' => $this->estado !== ''
                ? $consulta->lineasPorEstadoMateriales(
                    $this->estado, $this->porPagina,
                    $this->campoOrdenValido(self::COLUMNAS_ORDENABLES), $this->ordenDir,
                )
                : null,
            'estados' => EstadoMateriales::valores(),
        ];
    }
}; ?>

<x-ui.page title="Gestión por ítem"
           subtitle="Filtra las líneas de pedidos por liberar según su estado de materiales. Los ítems sin estado asignado no se muestran.">
    @if ($estado !== '')
        <x-slot:actions>
            <button wire:click="exportar" class="btn-secondary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                Exportar
            </button>
        </x-slot:actions>
    @endif

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
        <x-ui.card class="p-8 text-center text-sm text-ink-secondary dark:text-gray-500">
            Selecciona un estado de materiales para ver los ítems que lo tienen asignado.
        </x-ui.card>
    @else
        <x-ui.card flush>
            <div class="overflow-x-auto">
                <table class="data-table data-table--compact">
                    <thead>
                    <tr>
                        <x-ui.th-sort campo="pedido" :actual="$ordenCampo" :dir="$ordenDir">Pedido</x-ui.th-sort>
                        <x-ui.th-sort campo="nombre_cliente" :actual="$ordenCampo" :dir="$ordenDir">Cliente</x-ui.th-sort>
                        <x-ui.th-sort campo="destino" :actual="$ordenCampo" :dir="$ordenDir">Destino</x-ui.th-sort>
                        <x-ui.th-sort campo="item" :actual="$ordenCampo" :dir="$ordenDir">Ítem</x-ui.th-sort>
                        <x-ui.th-sort campo="descripcion" :actual="$ordenCampo" :dir="$ordenDir">Descripción</x-ui.th-sort>
                        <x-ui.th-sort campo="cantidad_pedida" :actual="$ordenCampo" :dir="$ordenDir" align="right">Cant. pedida</x-ui.th-sort>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($lineas as $l)
                        <tr wire:key="mat-{{ $l->pedido }}-{{ $l->item }}">
                            <td>
                                <a href="{{ route('pedidos.show', $l->pedido) }}" wire:navigate class="font-semibold text-brand-600 hover:text-brand-500">{{ $l->pedido }}</a>
                            </td>
                            <td class="font-medium text-ink-900 dark:text-gray-100">{{ $l->nombre_cliente }}</td>
                            <td class="whitespace-nowrap">{{ $l->destino }}</td>
                            <td class="font-semibold text-ink-900 dark:text-gray-100">{{ $l->item }}</td>
                            <td>{{ $l->descripcion }}</td>
                            <td class="num whitespace-nowrap">{{ number_format((float) $l->cantidad_pedida, 0, ',', '.') }} <span class="text-xs text-ink-secondary dark:text-gray-500">{{ $l->unidad_medida }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="!py-10 text-center text-ink-secondary dark:text-gray-500">Ningún ítem tiene asignado ese estado.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <div>{{ $lineas->links() }}</div>
    @endif
</x-ui.page>
