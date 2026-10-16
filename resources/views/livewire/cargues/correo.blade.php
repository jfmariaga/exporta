<?php

use App\Services\CorreoCarguesBuilder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Url]
    public string $destino = '';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    public function with(): array
    {
        $builder = new CorreoCarguesBuilder([
            'destino' => $this->destino ?: null,
            'desde' => $this->desde ?: null,
            'hasta' => $this->hasta ?: null,
        ]);

        return [
            'html' => $builder->html(),
            'texto' => $builder->texto(),
            'destinos' => DB::table('clientes')->whereNotNull('destino')->orderBy('destino')->distinct()->pluck('destino'),
        ];
    }
}; ?>

<x-ui.page title="Correo de cargues"
           subtitle="Pedidos con fecha tentativa de cargue, listos para comunicar a logística.">
    <x-ui.card class="p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
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
        <div class="correo-preview overflow-x-auto rounded-lg border border-ink-200 bg-ink-50/50 p-4 text-sm">{!! $html !!}</div>

        <div class="mt-5 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-ink-600">Texto plano</h3>
            <button type="button" class="btn-secondary"
                    @click="navigator.clipboard.writeText($refs.txt.value); copiado = true; setTimeout(() => copiado = false, 2000)">
                <span x-show="!copiado">Copiar</span>
                <span x-show="copiado" x-cloak>¡Copiado!</span>
            </button>
        </div>
        <textarea x-ref="txt" readonly rows="14"
                  class="mt-2 w-full rounded-lg border-ink-300 bg-ink-50/50 font-mono text-xs text-ink-700 focus:border-brand-500 focus:ring-brand-500">{{ $texto }}</textarea>
    </x-ui.card>
</x-ui.page>
