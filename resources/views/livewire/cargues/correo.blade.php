<?php

use App\Services\CorreoCarguesBuilder;
use App\Support\EstadosPedido;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Url]
    public string $pedido = '';

    #[Url]
    public string $destino = '';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    public function with(): array
    {
        $builder = new CorreoCarguesBuilder([
            'pedido' => $this->pedido ?: null,
            'destino' => $this->destino ?: null,
            'desde' => $this->desde ?: null,
            'hasta' => $this->hasta ?: null,
        ]);

        return [
            'html' => $builder->html(),
            'texto' => $builder->texto(),
            'destinos' => DB::table('clientes')->whereNotNull('destino')->orderBy('destino')->distinct()->pluck('destino'),
            'pedidos' => DB::table('lineas_pedido as l')
                ->join('seguimiento_pedidos as sp', 'sp.pedido', '=', 'l.pedido')
                ->whereIn('l.estado', EstadosPedido::POR_LIBERAR)
                ->where('l.item', 'not like', '7%')
                ->where('l.activo', true)
                ->whereNotNull('sp.fecha_tentativa_cargue')
                ->distinct()->orderBy('l.pedido')->pluck('l.pedido'),
        ];
    }
}; ?>

<x-ui.page title="Correo de cargues"
           subtitle="Pedidos con fecha tentativa de cargue, listos para comunicar a logística.">
    <x-ui.card class="p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="lbl">Pedido</label>
                <select wire:model.live="pedido" class="field-sm w-full">
                    <option value="">Todos</option>
                    @foreach ($pedidos as $p) <option value="{{ $p }}">{{ $p }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="lbl">Destino</label>
                <select wire:model.live="destino" class="field-sm w-full">
                    <option value="">Todos</option>
                    @foreach ($destinos as $d) <option value="{{ $d }}">{{ $d }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="lbl">Desde</label>
                <input type="date" wire:model.live="desde" class="field-sm w-full">
            </div>
            <div>
                <label class="lbl">Hasta</label>
                <input type="date" wire:model.live="hasta" class="field-sm w-full">
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="Vista HTML" x-data="{ copiado: false }">
        <div class="correo-preview overflow-x-auto rounded-lg border border-line bg-surface-alt/50 p-4 text-sm dark:border-white/10 dark:bg-white/5">{!! $html !!}</div>

        <div class="mt-5 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-ink-900 dark:text-gray-200">Texto plano</h3>
            <button type="button" class="btn-secondary"
                    @click="navigator.clipboard.writeText($refs.txt.value); copiado = true; setTimeout(() => copiado = false, 2000)">
                <span x-show="!copiado">Copiar</span>
                <span x-show="copiado" x-cloak>¡Copiado!</span>
            </button>
        </div>
        <textarea x-ref="txt" readonly rows="14"
                  class="mt-2 w-full rounded-lg border-line bg-surface-alt/50 font-mono text-xs text-ink-900 focus:border-brand-500 focus:ring-brand-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">{{ $texto }}</textarea>
    </x-ui.card>
</x-ui.page>
