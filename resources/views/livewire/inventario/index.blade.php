<?php

use App\Models\ParametroItem;
use App\Services\Inventario\DisponibilidadInventario;
use App\Services\Inventario\InventarioSync;
use App\Support\ConOrdenamiento;
use App\Support\ExportaExcel;
use App\Support\Permisos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app')] class extends Component
{
    use ConOrdenamiento, ExportaExcel;

    private const COLUMNAS_ORDENABLES = [
        'item', 'descripcion', 'cantidad_total', 'total_pedido', 'faltante',
        'asignado', 'disponible', 'estado', 'sincronizado_en',
    ];

    public ?array $resumenSync = null;

    public ?string $detalleAbierto = null;

    public function sincronizar(InventarioSync $fuente): void
    {
        abort_unless(auth()->user()->can(Permisos::ERP_IMPORTAR), 403);

        try {
            $this->resumenSync = $fuente->sincronizar();
        } catch (Throwable $e) {
            $this->addError('sync', $e->getMessage());

            return;
        }

        Log::info('inventario.sincronizado', ['actor' => auth()->id(), 'resumen' => $this->resumenSync]);
        session()->flash('status', 'Sincronización con SyncBox completada.');
    }

    public function alternarDetalle(string $item): void
    {
        $this->detalleAbierto = $this->detalleAbierto === $item ? null : $item;
    }

    /** @return Collection<int, array<string,mixed>> */
    private function estadosOrdenados(DisponibilidadInventario $disponibilidad): Collection
    {
        $estados = $disponibilidad->estados();

        $descripciones = ParametroItem::whereIn('item', $estados->pluck('item'))
            ->pluck('descripcion', 'item');

        $estados = $estados->map(fn (array $a) => $a + ['descripcion' => $descripciones[$a['item']] ?? null]);

        if ($campo = $this->campoOrdenValido(self::COLUMNAS_ORDENABLES)) {
            $estados = $estados->sortBy($campo, SORT_REGULAR, $this->ordenDir === 'desc')->values();
        }

        return $estados;
    }

    public function exportar(DisponibilidadInventario $disponibilidad): StreamedResponse
    {
        $estados = $this->estadosOrdenados($disponibilidad);

        return $this->descargarExcel('inventario.xlsx', [
            'Ítem', 'Descripción', 'Inventario', 'Total pedido', 'Faltante', 'Asignado', 'Disponible', 'Estado', 'Sincronizado',
        ], $estados->map(fn (array $a) => [
            $a['item'], $a['descripcion'], $a['cantidad_total'], $a['total_pedido'], $a['faltante'],
            $a['asignado'], $a['disponible'], $a['estado'],
            $a['sincronizado_en'] ? \Illuminate\Support\Carbon::parse($a['sincronizado_en'])->format('d/m/Y H:i') : null,
        ]));
    }

    public function with(DisponibilidadInventario $disponibilidad): array
    {
        $estados = $this->estadosOrdenados($disponibilidad);

        return [
            'estados' => $estados,
            'descripciones' => $estados->pluck('descripcion', 'item'),
            'puedeSincronizar' => auth()->user()->can(Permisos::ERP_IMPORTAR),
        ];
    }
}; ?>

<x-ui.page title="Inventario"
           subtitle="Existencias de exportación sincronizadas desde SyncBox, repartidas manualmente entre pedidos pendientes que comparten ítem.">
    <x-slot:actions>
        <button wire:click="exportar" class="btn-secondary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            Exportar
        </button>
        <a href="{{ route('inventario.detalle') }}" wire:navigate class="btn-secondary">Ver detalle por lote</a>
        @can('erp.importar')
            <button wire:click="sincronizar" class="btn-dark" wire:loading.attr="disabled" wire:target="sincronizar">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" wire:loading.remove wire:target="sincronizar"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                <span wire:loading.remove wire:target="sincronizar">Sincronizar inventario</span>
                <span wire:loading wire:target="sincronizar">Sincronizando…</span>
            </button>
        @endcan
    </x-slot:actions>

    @error('sync')
        <div class="rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger dark:border-danger-dark/20 dark:bg-danger-dark/10 dark:text-danger-dark">{{ $message }}</div>
    @enderror

    @php
        $etiquetasEstado = [
            'sobre_asignado' => ['sobre-asignado', 'red'],
            'sin_asignar' => ['sin asignar', 'amber'],
            'en_reparto' => ['en reparto', 'blue'],
            'distribuido' => ['distribuido', 'green'],
        ];
    @endphp

    <x-ui.card title="Estado de reparto" flush>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                <tr>
                    <x-ui.th-sort campo="item" :actual="$ordenCampo" :dir="$ordenDir">Ítem</x-ui.th-sort>
                    <x-ui.th-sort campo="descripcion" :actual="$ordenCampo" :dir="$ordenDir">Descripción</x-ui.th-sort>
                    <x-ui.th-sort campo="cantidad_total" :actual="$ordenCampo" :dir="$ordenDir" align="right">Inventario</x-ui.th-sort>
                    <x-ui.th-sort campo="total_pedido" :actual="$ordenCampo" :dir="$ordenDir" align="right">Total pedido</x-ui.th-sort>
                    <x-ui.th-sort campo="faltante" :actual="$ordenCampo" :dir="$ordenDir" align="right">Faltante</x-ui.th-sort>
                    <x-ui.th-sort campo="asignado" :actual="$ordenCampo" :dir="$ordenDir" align="right">Asignado</x-ui.th-sort>
                    <x-ui.th-sort campo="disponible" :actual="$ordenCampo" :dir="$ordenDir" align="right">Disponible</x-ui.th-sort>
                    <x-ui.th-sort campo="estado" :actual="$ordenCampo" :dir="$ordenDir">Estado</x-ui.th-sort>
                    <x-ui.th-sort campo="sincronizado_en" :actual="$ordenCampo" :dir="$ordenDir">Sincronizado</x-ui.th-sort>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($estados as $a)
                    @php [$etiqueta, $color] = $etiquetasEstado[$a['estado']]; @endphp
                    <tr wire:key="inv-{{ $a['item'] }}">
                        <td class="font-semibold text-ink-900 dark:text-gray-100">{{ $a['item'] }}</td>
                        <td>{{ $descripciones[$a['item']] ?? '—' }}</td>
                        <td class="num">{{ number_format($a['cantidad_total'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($a['total_pedido'], 0, ',', '.') }}</td>
                        <td class="num">
                            @if ($a['faltante'] > 0)
                                <x-ui.badge color="red" title="El total pedido supera el inventario sincronizado">
                                    -{{ number_format($a['faltante'], 0, ',', '.') }}
                                </x-ui.badge>
                            @else — @endif
                        </td>
                        <td class="num">{{ number_format($a['asignado'], 0, ',', '.') }}</td>
                        <td class="num font-semibold">{{ number_format($a['disponible'], 0, ',', '.') }}</td>
                        <td><x-ui.badge :color="$color">{{ $etiqueta }}</x-ui.badge></td>
                        <td class="text-xs text-ink-secondary dark:text-gray-500 whitespace-nowrap">
                            {{ $a['sincronizado_en'] ? \Illuminate\Support\Carbon::parse($a['sincronizado_en'])->format('d/m/Y H:i') : '—' }}
                        </td>
                        <td>
                            <button wire:click="alternarDetalle('{{ $a['item'] }}')" class="btn-link">
                                {{ $detalleAbierto === $a['item'] ? 'Ocultar' : 'Ver detalle' }}
                            </button>
                        </td>
                    </tr>
                    @if ($detalleAbierto === $a['item'] && $a['detalle']->isNotEmpty())
                        <tr wire:key="inv-detalle-{{ $a['item'] }}">
                            <td colspan="10" class="bg-surface-alt dark:bg-white/5">
                                <div class="overflow-x-auto p-2">
                                    <table class="data-table">
                                        <thead>
                                        <tr>
                                            <th>Bodega</th>
                                            <th>Ubicación</th>
                                            <th>Lote</th>
                                            <th class="!text-right">Cantidad</th>
                                            <th>Bloqueado</th>
                                            <th>Creado</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach ($a['detalle'] as $d)
                                            <tr>
                                                <td>{{ $d->warehouse ?? '—' }}</td>
                                                <td>{{ $d->ubication ?? '—' }}</td>
                                                <td>{{ $d->lote ?? '—' }}</td>
                                                <td class="num">{{ number_format((float) $d->cantidad, 0, ',', '.') }}</td>
                                                <td>{{ $d->bloqueado ? 'Sí' : 'No' }}</td>
                                                <td>{{ $d->creado?->format('d/m/Y') ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="10" class="!py-8 text-center text-ink-secondary dark:text-gray-500">Aún no hay inventario sincronizado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</x-ui.page>
