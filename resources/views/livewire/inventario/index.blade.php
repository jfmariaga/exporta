<?php

use App\Models\ParametroItem;
use App\Services\Inventario\DisponibilidadInventario;
use App\Services\Inventario\InventarioSync;
use App\Support\Permisos;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
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

    public function with(DisponibilidadInventario $disponibilidad): array
    {
        $estados = $disponibilidad->estados();

        $descripciones = ParametroItem::whereIn('item', $estados->pluck('item'))
            ->pluck('descripcion', 'item');

        return [
            'estados' => $estados,
            'descripciones' => $descripciones,
            'puedeSincronizar' => auth()->user()->can(Permisos::ERP_IMPORTAR),
        ];
    }
}; ?>

<x-ui.page title="Inventario"
           subtitle="Existencias de exportación sincronizadas desde SyncBox, repartidas manualmente entre pedidos pendientes que comparten ítem.">
    <x-slot:actions>
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
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
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
                    <th>Ítem</th>
                    <th>Descripción</th>
                    <th class="!text-right">Cant. total</th>
                    <th class="!text-right">Asignado</th>
                    <th class="!text-right">Disponible</th>
                    <th>Estado</th>
                    <th>Sincronizado</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($estados as $a)
                    @php [$etiqueta, $color] = $etiquetasEstado[$a['estado']]; @endphp
                    <tr wire:key="inv-{{ $a['item'] }}">
                        <td class="font-semibold text-ink-900">{{ $a['item'] }}</td>
                        <td>{{ $descripciones[$a['item']] ?? '—' }}</td>
                        <td class="num">{{ number_format($a['cantidad_total'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($a['asignado'], 0, ',', '.') }}</td>
                        <td class="num font-semibold">{{ number_format($a['disponible'], 0, ',', '.') }}</td>
                        <td><x-ui.badge :color="$color">{{ $etiqueta }}</x-ui.badge></td>
                        <td class="text-xs text-ink-400 whitespace-nowrap">
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
                            <td colspan="8" class="bg-ink-50">
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
                    <tr><td colspan="8" class="!py-8 text-center text-ink-400">Aún no hay inventario sincronizado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</x-ui.page>
