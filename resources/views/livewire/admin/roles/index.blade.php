<?php

use App\Models\Role;
use App\Support\AdministradorGuard;
use App\Support\Permisos;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\PermissionRegistrar;

new #[Layout('layouts.app')] class extends Component
{
    public bool $mostrandoForm = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public array $permisosSel = [];

    private function autorizar(): void
    {
        abort_unless(auth()->user()->can(Permisos::ADMIN_ROLES), 403);
    }

    public function nuevo(): void
    {
        $this->autorizar();
        $this->reset('editandoId', 'nombre', 'permisosSel');
        $this->mostrandoForm = true;
    }

    public function editar(int $id): void
    {
        $this->autorizar();
        $rol = Role::findOrFail($id);
        $this->editandoId = $rol->id;
        $this->nombre = $rol->name;
        $this->permisosSel = $rol->permissions->pluck('name')->all();
        $this->mostrandoForm = true;
    }

    public function guardar(): void
    {
        $this->autorizar();

        $this->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($this->editandoId)],
            'permisosSel' => ['array'],
            'permisosSel.*' => [Rule::in(Permisos::TODOS)],
        ]);

        if ($this->editandoId) {
            $rol = Role::findOrFail($this->editandoId);
            $nombre = AdministradorGuard::esRolProtegido($rol) ? $rol->name : $this->nombre;
            $rol->update(['name' => $nombre]);
        } else {
            $rol = Role::create(['name' => $this->nombre, 'guard_name' => 'web']);
        }

        $rol->syncPermissions($this->permisosSel);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Log::info('admin.rol.guardado', ['actor' => auth()->id(), 'rol' => $rol->name, 'permisos' => $this->permisosSel]);

        $this->mostrandoForm = false;
        $this->reset('editandoId', 'nombre', 'permisosSel');
        session()->flash('status', 'Rol guardado.');
    }

    public function eliminar(int $id): void
    {
        $this->autorizar();
        $rol = Role::findOrFail($id);

        abort_if(AdministradorGuard::esRolProtegido($rol), 422, 'El rol Administrador está protegido.');
        abort_if($rol->users()->exists(), 422, 'El rol tiene usuarios asignados.');

        $nombre = $rol->name;
        $rol->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Log::info('admin.rol.eliminado', ['actor' => auth()->id(), 'rol' => $nombre]);
        session()->flash('status', 'Rol eliminado.');
    }

    public function with(): array
    {
        return [
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('name')->get(),
            'permisos' => Permisos::TODOS,
            'etiquetas' => Permisos::etiquetas(),
        ];
    }
}; ?>

<x-ui.page title="Roles" subtitle="Agrupaciones de permisos que se asignan a los usuarios.">
    <x-slot:actions>
        <a href="{{ route('admin.permisos.index') }}" wire:navigate class="btn-secondary">Catálogo de permisos</a>
        <button wire:click="nuevo" class="btn-primary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            Nuevo rol
        </button>
    </x-slot:actions>

    @if ($mostrandoForm)
        <x-ui.card :title="$editandoId ? 'Editar rol' : 'Nuevo rol'">
            <form wire:submit="guardar" class="max-w-xl space-y-4">
                <div>
                    <label class="lbl">Nombre</label>
                    <input wire:model="nombre" class="field w-full">
                    @error('nombre') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Permisos</label>
                    <div class="space-y-1.5 rounded-lg border border-ink-200 p-3">
                        @foreach ($permisos as $p)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="permisosSel" value="{{ $p }}" class="rounded border-ink-300 text-brand-600 focus:ring-brand-500">
                                <span class="font-mono text-xs text-ink-400">{{ $p }}</span>
                                <span class="text-ink-700">— {{ $etiquetas[$p] ?? $p }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="flex gap-3">
                    <button class="btn-primary">Guardar</button>
                    <button type="button" wire:click="$set('mostrandoForm', false)" class="btn-secondary">Cancelar</button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card flush>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                <tr><th>Rol</th><th class="!text-right">Usuarios</th><th class="!text-right">Permisos</th><th class="!text-right">Acciones</th></tr>
                </thead>
                <tbody>
                @foreach ($roles as $rol)
                    <tr wire:key="r-{{ $rol->id }}">
                        <td class="font-medium text-ink-900">
                            {{ $rol->name }}
                            @if ($rol->protegido)<x-ui.badge color="indigo" class="ml-1">protegido</x-ui.badge>@endif
                        </td>
                        <td class="num">{{ $rol->users_count }}</td>
                        <td class="num">{{ $rol->permissions_count }}</td>
                        <td class="num whitespace-nowrap">
                            <div class="inline-flex items-center gap-3">
                                <button wire:click="editar({{ $rol->id }})" class="btn-link">Editar</button>
                                @unless ($rol->protegido)
                                    <button type="button" x-data x-on:click="Swal.fire({
                                                title: '¿Eliminar rol?',
                                                text: @js('¿Eliminar el rol '.$rol->name.'?'),
                                                icon: 'warning',
                                                showCancelButton: true,
                                                confirmButtonText: 'Eliminar',
                                                cancelButtonText: 'Cancelar',
                                                confirmButtonColor: '#dc2626',
                                                reverseButtons: true,
                                            }).then((r) => { if (r.isConfirmed) $wire.eliminar({{ $rol->id }}) })" class="btn-danger-link">Eliminar</button>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>
</x-ui.page>
