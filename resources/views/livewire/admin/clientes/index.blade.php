<?php

use App\Models\Cliente;
use App\Support\Permisos;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $q = '';

    public bool $mostrandoForm = false;

    public ?int $editandoId = null;

    public string $nit_cliente = '';

    public string $cliente = '';

    public string $destino = '';

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
        $this->reset('editandoId', 'nit_cliente', 'cliente', 'destino');
        $this->mostrandoForm = true;
    }

    public function editar(int $id): void
    {
        $this->autorizar();
        $c = Cliente::findOrFail($id);
        $this->editandoId = $c->id;
        $this->nit_cliente = $c->nit_cliente;
        $this->cliente = (string) $c->cliente;
        $this->destino = (string) $c->destino;
        $this->mostrandoForm = true;
    }

    public function guardar(): void
    {
        $this->autorizar();

        $datos = $this->validate([
            'nit_cliente' => ['required', 'string', 'max:30', Rule::unique('clientes', 'nit_cliente')->ignore($this->editandoId)],
            'cliente' => ['nullable', 'string', 'max:255'],
            'destino' => ['nullable', 'string', 'max:80'],
        ]);

        if ($this->editandoId) {
            Cliente::findOrFail($this->editandoId)->update($datos);
            $msg = 'Cliente actualizado.';
        } else {
            Cliente::create($datos);
            $msg = 'Cliente creado.';
        }

        $this->mostrandoForm = false;
        $this->reset('editandoId', 'nit_cliente', 'cliente', 'destino');
        session()->flash('status', $msg);
    }

    public function eliminar(int $id): void
    {
        $this->autorizar();
        Cliente::findOrFail($id)->delete();
        session()->flash('status', 'Cliente eliminado.');
    }

    public function with(): array
    {
        $clientes = Cliente::query()
            ->when($this->q, fn ($x) => $x->where(fn ($w) => $w
                ->where('nit_cliente', 'like', "%{$this->q}%")
                ->orWhere('cliente', 'like', "%{$this->q}%")
                ->orWhere('destino', 'like', "%{$this->q}%")))
            ->orderBy('cliente')->paginate(15);

        return ['clientes' => $clientes];
    }
}; ?>

<x-ui.page title="Clientes" subtitle="NIT, nombre y destino usados para cruzar el destino de cada pedido.">
    <x-slot:actions>
        <button wire:click="nuevo" class="btn-primary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            Nuevo cliente
        </button>
    </x-slot:actions>

    @if ($mostrandoForm)
        <x-ui.card :title="$editandoId ? 'Editar cliente' : 'Nuevo cliente'">
            <form wire:submit="guardar" class="grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="lbl">NIT</label>
                    <input wire:model="nit_cliente" class="field w-full">
                    @error('nit_cliente') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Cliente</label>
                    <input wire:model="cliente" class="field w-full">
                    @error('cliente') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Destino</label>
                    <input wire:model="destino" class="field w-full">
                    @error('destino') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div class="flex gap-3 sm:col-span-3">
                    <button class="btn-primary">Guardar</button>
                    <button type="button" wire:click="$set('mostrandoForm', false)" class="btn-secondary">Cancelar</button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card class="p-4">
        <label class="lbl">Buscar</label>
        <input wire:model.live.debounce.400ms="q" placeholder="NIT, cliente o destino" class="field-sm w-full sm:max-w-sm">
    </x-ui.card>

    <x-ui.card flush>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                <tr><th>NIT</th><th>Cliente</th><th>Destino</th><th class="!text-right">Acciones</th></tr>
                </thead>
                <tbody>
                @foreach ($clientes as $c)
                    <tr wire:key="c-{{ $c->id }}">
                        <td class="num">{{ $c->nit_cliente }}</td>
                        <td class="font-medium text-ink-900">{{ $c->cliente ?: '—' }}</td>
                        <td>{{ $c->destino ?: '—' }}</td>
                        <td class="num whitespace-nowrap">
                            <div class="inline-flex items-center gap-3">
                                <button wire:click="editar({{ $c->id }})" class="btn-link">Editar</button>
                                <button type="button" x-data x-on:click="Swal.fire({
                                            title: '¿Eliminar cliente?',
                                            text: @js('¿Eliminar al cliente '.$c->nit_cliente.'?'),
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonText: 'Eliminar',
                                            cancelButtonText: 'Cancelar',
                                            confirmButtonColor: '#dc2626',
                                            reverseButtons: true,
                                        }).then((r) => { if (r.isConfirmed) $wire.eliminar({{ $c->id }}) })" class="btn-danger-link">Eliminar</button>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <div>{{ $clientes->links() }}</div>
</x-ui.page>
