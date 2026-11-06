<?php

use App\Models\ActividadDestinoAuditoria;
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

    private const COLUMNAS_ORDENABLES = ['pedido', 'item', 'destino', 'actividad', 'cumplida', 'usuario_nombre', 'created_at'];

    #[Url]
    public string $pedido = '';

    #[Url]
    public string $item = '';

    public function updated($campo): void
    {
        if (in_array($campo, ['pedido', 'item'], true)) {
            $this->resetPage();
        }
    }

    private function autorizar(): void
    {
        abort_unless(auth()->user()->can(Permisos::ADMIN_USUARIOS), 403);
    }

    private function queryAuditorias(): Builder
    {
        $this->autorizar();

        return ActividadDestinoAuditoria::query()
            ->when($this->pedido !== '', fn ($x) => $x->where('pedido', 'like', "%{$this->pedido}%"))
            ->when($this->item !== '', fn ($x) => $x->where('item', 'like', "%{$this->item}%"))
            ->orderBy($this->campoOrdenValido(self::COLUMNAS_ORDENABLES) ?? 'created_at', $this->ordenCampo ? $this->ordenDir : 'desc');
    }

    public function exportar(): StreamedResponse
    {
        $auditorias = $this->queryAuditorias()->get();

        return $this->descargarExcel('historial-actividad-por-destino.xlsx', [
            'Pedido', 'Ítem', 'Destino', 'Actividad', 'Cumplida', 'Usuario', 'Fecha y hora',
        ], $auditorias->map(fn (ActividadDestinoAuditoria $a) => [
            $a->pedido, $a->item, $a->destino, $a->actividad, $a->cumplida ? 'Sí' : 'No',
            $a->usuario_nombre, $a->created_at?->format('d/m/Y H:i'),
        ]));
    }

    public function with(): array
    {
        return ['auditorias' => $this->queryAuditorias()->paginate(25)];
    }
}; ?>

<x-ui.page title="Historial de actividad por destino"
           subtitle="Cada vez que alguien marca o desmarca una condición como cumplida en el detalle de un pedido.">
    <x-slot:actions>
        <button wire:click="exportar" class="btn-secondary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            Exportar
        </button>
        <a href="{{ route('admin.actividad-destino.index') }}" wire:navigate class="btn-secondary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Volver
        </a>
    </x-slot:actions>

    <x-ui.card class="p-3">
        <div class="flex flex-wrap items-end gap-2">
            <div>
                <label class="lbl">Pedido</label>
                <input wire:model.live.debounce.400ms="pedido" placeholder="Número de pedido" class="field-sm w-40">
            </div>
            <div>
                <label class="lbl">Ítem</label>
                <input wire:model.live.debounce.400ms="item" placeholder="Código" class="field-sm w-32">
            </div>
        </div>
    </x-ui.card>

    <x-ui.card flush>
        <div class="overflow-x-auto">
            <table class="data-table data-table--compact">
                <thead>
                <tr>
                    <x-ui.th-sort campo="pedido" :actual="$ordenCampo" :dir="$ordenDir">Pedido</x-ui.th-sort>
                    <x-ui.th-sort campo="item" :actual="$ordenCampo" :dir="$ordenDir">Ítem</x-ui.th-sort>
                    <x-ui.th-sort campo="destino" :actual="$ordenCampo" :dir="$ordenDir">Destino</x-ui.th-sort>
                    <x-ui.th-sort campo="actividad" :actual="$ordenCampo" :dir="$ordenDir">Actividad</x-ui.th-sort>
                    <x-ui.th-sort campo="cumplida" :actual="$ordenCampo" :dir="$ordenDir">Cumplida</x-ui.th-sort>
                    <x-ui.th-sort campo="usuario_nombre" :actual="$ordenCampo" :dir="$ordenDir">Usuario</x-ui.th-sort>
                    <x-ui.th-sort campo="created_at" :actual="$ordenCampo" :dir="$ordenDir">Fecha y hora</x-ui.th-sort>
                </tr>
                </thead>
                <tbody>
                @forelse ($auditorias as $a)
                    <tr wire:key="aud-{{ $a->id }}">
                        <td>
                            <a href="{{ route('pedidos.show', $a->pedido) }}" wire:navigate class="font-semibold text-brand-600 hover:text-brand-500">{{ $a->pedido }}</a>
                        </td>
                        <td class="font-semibold text-ink-900 dark:text-gray-100">{{ $a->item }}</td>
                        <td class="whitespace-nowrap">{{ $a->destino }}</td>
                        <td>{{ $a->actividad ?: '—' }}</td>
                        <td><x-ui.badge :color="$a->cumplida ? 'green' : 'red'">{{ $a->cumplida ? 'Sí' : 'No' }}</x-ui.badge></td>
                        <td>{{ $a->usuario_nombre ?: '—' }}</td>
                        <td class="whitespace-nowrap">{{ $a->created_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="!py-10 text-center text-ink-secondary dark:text-gray-500">Sin cambios registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <div>{{ $auditorias->links() }}</div>
</x-ui.page>
