<?php

use App\Models\ParametroItem;
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

    private const COLUMNAS_ORDENABLES = ['item', 'descripcion', 'categoria', 'factor_kg', 'embalaje', 'volumen'];

    public string $q = '';

    public bool $mostrandoForm = false;

    public ?int $editandoId = null;

    public string $item = '';

    public string $descripcion = '';

    public ?string $factor_kg = null;

    public string $categoria = '';

    public ?string $embalaje = null;

    public ?string $volumen = null;

    public ?string $num_tendidos = null;

    public ?string $cajas_pallet = null;

    public ?string $altura_caja = null;

    public ?string $cajas_por_nivel = null;

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
        $this->reset(
            'editandoId', 'item', 'descripcion', 'factor_kg', 'categoria',
            'embalaje', 'volumen', 'num_tendidos', 'cajas_pallet', 'altura_caja', 'cajas_por_nivel'
        );
        $this->mostrandoForm = true;
    }

    public function editar(int $id): void
    {
        $this->autorizar();
        $p = ParametroItem::findOrFail($id);
        $this->editandoId = $p->id;
        $this->item = $p->item;
        $this->descripcion = (string) $p->descripcion;
        $this->factor_kg = $p->factor_kg !== null ? (string) $p->factor_kg : null;
        $this->categoria = (string) $p->categoria;
        $this->embalaje = $p->embalaje !== null ? (string) $p->embalaje : null;
        $this->volumen = $p->volumen !== null ? (string) $p->volumen : null;
        $this->num_tendidos = $p->num_tendidos !== null ? (string) $p->num_tendidos : null;
        $this->cajas_pallet = $p->cajas_pallet !== null ? (string) $p->cajas_pallet : null;
        $this->altura_caja = $p->altura_caja !== null ? (string) $p->altura_caja : null;
        $this->cajas_por_nivel = $p->cajas_por_nivel !== null ? (string) $p->cajas_por_nivel : null;
        $this->mostrandoForm = true;
    }

    public function guardar(): void
    {
        $this->autorizar();

        $datos = $this->validate([
            'item' => ['required', 'string', 'max:30', Rule::unique('parametros_item', 'item')->ignore($this->editandoId)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'factor_kg' => ['nullable', 'numeric'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'embalaje' => ['nullable', 'integer'],
            'volumen' => ['nullable', 'numeric'],
            'num_tendidos' => ['nullable', 'integer'],
            'cajas_pallet' => ['nullable', 'integer'],
            'altura_caja' => ['nullable', 'numeric'],
            'cajas_por_nivel' => ['nullable', 'numeric'],
        ]);

        if ($this->editandoId) {
            ParametroItem::findOrFail($this->editandoId)->update($datos);
            $msg = 'Parámetro actualizado.';
        } else {
            ParametroItem::create($datos);
            $msg = 'Parámetro creado.';
        }

        $this->mostrandoForm = false;
        $this->reset(
            'editandoId', 'item', 'descripcion', 'factor_kg', 'categoria',
            'embalaje', 'volumen', 'num_tendidos', 'cajas_pallet', 'altura_caja', 'cajas_por_nivel'
        );
        session()->flash('status', $msg);
    }

    public function eliminar(int $id): void
    {
        $this->autorizar();
        ParametroItem::findOrFail($id)->delete();
        session()->flash('status', 'Parámetro eliminado.');
    }

    private function queryParametros(): Builder
    {
        return ParametroItem::query()
            ->when($this->q, fn ($x) => $x->where(fn ($w) => $w
                ->where('item', 'like', "%{$this->q}%")
                ->orWhere('descripcion', 'like', "%{$this->q}%")
                ->orWhere('categoria', 'like', "%{$this->q}%")))
            ->orderBy($this->campoOrdenValido(self::COLUMNAS_ORDENABLES) ?? 'item', $this->ordenDir);
    }

    public function exportar(): StreamedResponse
    {
        $parametros = $this->queryParametros()->get();

        return $this->descargarExcel('parametros-item.xlsx', [
            'Ítem', 'Descripción', 'Categoría', 'Factor kg', 'Embalaje', 'Volumen',
        ], $parametros->map(fn (ParametroItem $p) => [
            $p->item, $p->descripcion, $p->categoria,
            $p->factor_kg !== null ? (float) $p->factor_kg : null,
            $p->embalaje, $p->volumen !== null ? (float) $p->volumen : null,
        ]));
    }

    public function with(): array
    {
        return ['parametros' => $this->queryParametros()->paginate(15)];
    }
}; ?>

<x-ui.page title="Parámetros de ítem" subtitle="Factor kg y datos de embalaje usados para calcular toneladas.">
    <x-slot:actions>
        <button wire:click="exportar" class="btn-secondary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            Exportar
        </button>
        <a href="{{ route('admin.actividad-destino.index') }}" wire:navigate class="btn-secondary">Ver actividad por destino</a>
        <button wire:click="nuevo" class="btn-primary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            Nuevo parámetro
        </button>
    </x-slot:actions>

    @if ($mostrandoForm)
        <x-ui.card :title="$editandoId ? 'Editar parámetro' : 'Nuevo parámetro'">
            <form wire:submit="guardar" class="grid max-w-3xl grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label class="lbl">Ítem</label>
                    <input wire:model="item" class="field w-full">
                    @error('item') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div class="col-span-2">
                    <label class="lbl">Descripción</label>
                    <input wire:model="descripcion" class="field w-full">
                    @error('descripcion') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Categoría</label>
                    <input wire:model="categoria" class="field w-full">
                    @error('categoria') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Factor kg</label>
                    <input wire:model="factor_kg" type="number" step="0.000001" class="field w-full">
                    @error('factor_kg') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Embalaje</label>
                    <input wire:model="embalaje" type="number" class="field w-full">
                    @error('embalaje') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Volumen</label>
                    <input wire:model="volumen" type="number" step="0.0001" class="field w-full">
                    @error('volumen') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Tendidos</label>
                    <input wire:model="num_tendidos" type="number" class="field w-full">
                    @error('num_tendidos') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Cajas / pallet</label>
                    <input wire:model="cajas_pallet" type="number" class="field w-full">
                    @error('cajas_pallet') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Altura caja</label>
                    <input wire:model="altura_caja" type="number" step="0.01" class="field w-full">
                    @error('altura_caja') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Cajas / nivel</label>
                    <input wire:model="cajas_por_nivel" type="number" step="0.0001" class="field w-full">
                    @error('cajas_por_nivel') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div class="col-span-2 flex gap-3 sm:col-span-4">
                    <button class="btn-primary">Guardar</button>
                    <button type="button" wire:click="$set('mostrandoForm', false)" class="btn-secondary">Cancelar</button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card class="p-4">
        <label class="lbl">Buscar</label>
        <input wire:model.live.debounce.400ms="q" placeholder="Ítem, descripción o categoría" class="field-sm w-full sm:max-w-sm">
    </x-ui.card>

    <x-ui.card flush>
        <div class="overflow-x-auto">
            <table class="data-table data-table--compact">
                <thead>
                <tr>
                    <x-ui.th-sort campo="item" :actual="$ordenCampo" :dir="$ordenDir">Ítem</x-ui.th-sort>
                    <x-ui.th-sort campo="descripcion" :actual="$ordenCampo" :dir="$ordenDir">Descripción</x-ui.th-sort>
                    <x-ui.th-sort campo="categoria" :actual="$ordenCampo" :dir="$ordenDir">Categoría</x-ui.th-sort>
                    <x-ui.th-sort campo="factor_kg" :actual="$ordenCampo" :dir="$ordenDir" align="right">Factor kg</x-ui.th-sort>
                    <x-ui.th-sort campo="embalaje" :actual="$ordenCampo" :dir="$ordenDir" align="right">Embalaje</x-ui.th-sort>
                    <x-ui.th-sort campo="volumen" :actual="$ordenCampo" :dir="$ordenDir" align="right">Volumen</x-ui.th-sort>
                    <th class="!text-right">Acciones</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($parametros as $p)
                    <tr wire:key="p-{{ $p->id }}">
                        <td class="num">{{ $p->item }}</td>
                        <td class="font-medium text-ink-900 dark:text-gray-100">{{ $p->descripcion ?: '—' }}</td>
                        <td>{{ $p->categoria ?: '—' }}</td>
                        <td class="num">{{ \App\Support\Numero::decimal($p->factor_kg !== null ? (float) $p->factor_kg : null, 6) }}</td>
                        <td class="num">{{ $p->embalaje !== null ? number_format((float) $p->embalaje, 0, ',', '.') : '—' }}</td>
                        <td class="num">{{ \App\Support\Numero::decimal($p->volumen !== null ? (float) $p->volumen : null, 4) }}</td>
                        <td class="num whitespace-nowrap">
                            <div class="inline-flex items-center gap-3">
                                <button wire:click="editar({{ $p->id }})" class="btn-link">Editar</button>
                                <button type="button" x-data x-on:click="Swal.fire({
                                            title: '¿Eliminar parámetro?',
                                            text: @js('¿Eliminar el ítem '.$p->item.'?'),
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonText: 'Eliminar',
                                            cancelButtonText: 'Cancelar',
                                            confirmButtonColor: '#7A1E1E',
                                            reverseButtons: true,
                                        }).then((r) => { if (r.isConfirmed) $wire.eliminar({{ $p->id }}) })" class="btn-danger-link">Eliminar</button>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <div>{{ $parametros->links() }}</div>
</x-ui.page>
