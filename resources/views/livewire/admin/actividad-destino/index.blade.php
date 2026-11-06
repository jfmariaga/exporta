<?php

use App\Models\ActividadDestino;
use App\Support\ConOrdenamiento;
use App\Support\ExportaExcel;
use App\Support\Permisos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app')] class extends Component
{
    use ConOrdenamiento, ExportaExcel, WithPagination;

    private const COLUMNAS_ORDENABLES = ['item', 'destino', 'categoria', 'actividad'];

    public string $q = '';

    public bool $mostrandoForm = false;

    public ?int $editandoId = null;

    public string $item = '';

    public string $destino = '';

    public string $categoria = '';

    public string $actividad = '';

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    private function autorizar(): void
    {
        abort_unless(auth()->user()->can(Permisos::ADMIN_USUARIOS), 403);
    }

    public function nuevo(): void
    {
        $this->autorizar();
        $this->reset('editandoId', 'item', 'destino', 'categoria', 'actividad');
        $this->mostrandoForm = true;
    }

    public function editar(int $id): void
    {
        $this->autorizar();
        $a = ActividadDestino::findOrFail($id);
        $this->editandoId = $a->id;
        $this->item = $a->item;
        $this->destino = $a->destino;
        $this->categoria = (string) $a->categoria;
        $this->actividad = (string) $a->actividad;
        $this->mostrandoForm = true;
    }

    public function guardar(): void
    {
        $this->autorizar();

        $datos = $this->validate([
            'item' => [
                'required', 'string', 'max:30',
                Rule::unique('actividades_destino', 'item')->where('destino', $this->destino)->ignore($this->editandoId),
            ],
            'destino' => ['required', 'string', 'max:80'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'actividad' => ['nullable', 'string', 'max:120'],
        ]);

        if ($this->editandoId) {
            ActividadDestino::findOrFail($this->editandoId)->update($datos);
            $msg = 'Actividad actualizada.';
        } else {
            ActividadDestino::create($datos);
            $msg = 'Actividad creada.';
        }

        $this->mostrandoForm = false;
        $this->reset('editandoId', 'item', 'destino', 'categoria', 'actividad');
        session()->flash('status', $msg);
    }

    public function eliminar(int $id): void
    {
        $this->autorizar();
        ActividadDestino::findOrFail($id)->delete();
        session()->flash('status', 'Actividad eliminada.');
    }

    private function queryActividades(): Builder
    {
        return ActividadDestino::query()
            ->when($this->q, fn ($x) => $x->where(fn ($w) => $w
                ->where('item', 'like', "%{$this->q}%")
                ->orWhere('destino', 'like', "%{$this->q}%")
                ->orWhere('categoria', 'like', "%{$this->q}%")
                ->orWhere('actividad', 'like', "%{$this->q}%")))
            ->orderBy($this->campoOrdenValido(self::COLUMNAS_ORDENABLES) ?? 'item', $this->ordenDir);
    }

    public function exportar(): StreamedResponse
    {
        $actividades = $this->queryActividades()->get();

        return $this->descargarExcel('actividad-por-destino.xlsx', [
            'Ítem', 'Destino', 'Categoría', 'Actividad',
        ], $actividades->map(fn (ActividadDestino $a) => [$a->item, $a->destino, $a->categoria, $a->actividad]));
    }

    public function with(): array
    {
        return ['actividades' => $this->queryActividades()->paginate(15)];
    }
}; ?>

<x-ui.page title="Actividad por destino"
           subtitle="Condición especial que debe cumplirse para un ítem en un destino dado (ej. gestionar pedido, falta etiqueta).">
    <x-slot:actions>
        <button wire:click="exportar" class="btn-secondary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            Exportar
        </button>
        <a href="{{ route('admin.actividad-destino.historial') }}" wire:navigate class="btn-secondary">Ver historial</a>
        <a href="{{ route('admin.parametros-item.index') }}" wire:navigate class="btn-secondary">Ver parámetros de ítem</a>
        <button wire:click="nuevo" class="btn-primary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            Nueva actividad
        </button>
    </x-slot:actions>

    @if ($mostrandoForm)
        <x-ui.card :title="$editandoId ? 'Editar actividad' : 'Nueva actividad'">
            <form wire:submit="guardar" class="grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl">Ítem</label>
                    <input wire:model="item" class="field w-full">
                    @error('item') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Destino</label>
                    <input wire:model="destino" class="field w-full">
                    @error('destino') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Categoría</label>
                    <input wire:model="categoria" class="field w-full">
                    @error('categoria') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Actividad</label>
                    <input wire:model="actividad" class="field w-full" placeholder="Ej. GESTIONAR PEDIDO">
                    @error('actividad') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div class="flex gap-3 sm:col-span-2">
                    <button class="btn-primary">Guardar</button>
                    <button type="button" wire:click="$set('mostrandoForm', false)" class="btn-secondary">Cancelar</button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card class="p-4">
        <label class="lbl">Buscar</label>
        <input wire:model.live.debounce.400ms="q" placeholder="Ítem, destino, categoría o actividad" class="field-sm w-full sm:max-w-sm">
    </x-ui.card>

    <x-ui.card flush>
        <div class="overflow-x-auto">
            <table class="data-table data-table--compact">
                <thead>
                <tr>
                    <x-ui.th-sort campo="item" :actual="$ordenCampo" :dir="$ordenDir">Ítem</x-ui.th-sort>
                    <x-ui.th-sort campo="destino" :actual="$ordenCampo" :dir="$ordenDir">Destino</x-ui.th-sort>
                    <x-ui.th-sort campo="categoria" :actual="$ordenCampo" :dir="$ordenDir">Categoría</x-ui.th-sort>
                    <x-ui.th-sort campo="actividad" :actual="$ordenCampo" :dir="$ordenDir">Actividad</x-ui.th-sort>
                    <th class="!text-right">Acciones</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($actividades as $a)
                    <tr wire:key="a-{{ $a->id }}">
                        <td class="num">{{ $a->item }}</td>
                        <td class="whitespace-nowrap">{{ $a->destino }}</td>
                        <td>{{ $a->categoria ?: '—' }}</td>
                        <td class="font-medium text-ink-900 dark:text-gray-100">{{ $a->actividad ?: '—' }}</td>
                        <td class="num whitespace-nowrap">
                            <div class="inline-flex items-center gap-3">
                                <button wire:click="editar({{ $a->id }})" class="btn-link">Editar</button>
                                <button type="button" x-data x-on:click="Swal.fire({
                                            title: '¿Eliminar actividad?',
                                            text: @js('¿Eliminar la actividad del ítem '.$a->item.' para '.$a->destino.'?'),
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonText: 'Eliminar',
                                            cancelButtonText: 'Cancelar',
                                            confirmButtonColor: '#7A1E1E',
                                            reverseButtons: true,
                                        }).then((r) => { if (r.isConfirmed) $wire.eliminar({{ $a->id }}) })" class="btn-danger-link">Eliminar</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="!py-10 text-center text-ink-secondary dark:text-gray-500">Sin resultados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <div>{{ $actividades->links() }}</div>
</x-ui.page>
