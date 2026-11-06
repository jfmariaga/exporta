<?php

use App\Models\Role;
use App\Models\User;
use App\Support\AdministradorGuard;
use App\Support\ConOrdenamiento;
use App\Support\ExportaExcel;
use App\Support\Permisos;
use App\Support\RolesBase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app')] class extends Component
{
    use ConOrdenamiento, ExportaExcel, WithPagination;

    private const COLUMNAS_ORDENABLES = ['name', 'email', 'activo'];

    public string $q = '';

    public string $filtroRol = '';

    public string $filtroEstado = '';

    // Formulario
    public bool $mostrandoForm = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $correo = '';

    public ?string $clave = null;

    public ?string $clave_confirmation = null;

    public array $rolesSel = [];

    // Reset de contraseña
    public ?int $resetId = null;

    public ?string $resetPw = null;

    public ?string $resetPw_confirmation = null;

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroRol(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroEstado(): void
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
        $this->reset('editandoId', 'nombre', 'correo', 'clave', 'clave_confirmation', 'rolesSel');
        $this->mostrandoForm = true;
    }

    public function editar(int $id): void
    {
        $this->autorizar();
        $u = User::findOrFail($id);
        $this->editandoId = $u->id;
        $this->nombre = $u->name;
        $this->correo = $u->email;
        $this->clave = $this->clave_confirmation = null;
        $this->rolesSel = $u->getRoleNames()->all();
        $this->mostrandoForm = true;
    }

    public function guardar(): void
    {
        $this->autorizar();

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editandoId)],
            'clave' => [$this->editandoId ? 'nullable' : 'required', 'nullable', 'string', 'min:8', 'same:clave_confirmation'],
            'rolesSel' => ['required', 'array', 'min:1'],
            'rolesSel.*' => [Rule::exists('roles', 'name')],
        ]);

        if ($this->editandoId) {
            $u = User::findOrFail($this->editandoId);
            if ($u->hasRole(RolesBase::ADMINISTRADOR) && ! in_array(RolesBase::ADMINISTRADOR, $this->rolesSel, true)) {
                AdministradorGuard::garantizarNoEsUltimo($u, 'rolesSel');
            }
            $u->update(['name' => $this->nombre, 'email' => $this->correo]);
            if (! empty($this->clave)) {
                $u->update(['password' => Hash::make($this->clave), 'password_cambiada_en' => now()]);
            }
            $u->syncRoles($this->rolesSel);
            Log::info('admin.usuario.editado', ['actor' => auth()->id(), 'usuario' => $u->id]);
            $msg = 'Usuario actualizado.';
        } else {
            $u = User::create([
                'name' => $this->nombre,
                'email' => $this->correo,
                'password' => Hash::make($this->clave),
                'activo' => true,
            ]);
            $u->syncRoles($this->rolesSel);
            Log::info('admin.usuario.creado', ['actor' => auth()->id(), 'usuario' => $u->id]);
            $msg = 'Usuario creado.';
        }

        $this->mostrandoForm = false;
        $this->reset('editandoId', 'nombre', 'correo', 'clave', 'clave_confirmation', 'rolesSel');
        session()->flash('status', $msg);
    }

    public function toggleActivo(int $id): void
    {
        $this->autorizar();
        $u = User::findOrFail($id);
        if ($u->activo) {
            AdministradorGuard::garantizarNoEsUltimo($u);
        }
        $u->update(['activo' => ! $u->activo]);
        Log::info('admin.usuario.activo_cambiado', ['actor' => auth()->id(), 'usuario' => $u->id, 'activo' => $u->activo]);
        session()->flash('status', $u->activo ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    public function eliminar(int $id): void
    {
        $this->autorizar();
        $u = User::findOrFail($id);
        abort_if($u->id === auth()->id(), 422, 'No puedes eliminar tu propio usuario.');
        AdministradorGuard::garantizarNoEsUltimo($u);
        $u->delete();
        Log::info('admin.usuario.eliminado', ['actor' => auth()->id(), 'usuario' => $id]);
        session()->flash('status', 'Usuario eliminado.');
    }

    public function pedirReset(int $id): void
    {
        $this->autorizar();
        $this->resetId = $id;
        $this->resetPw = $this->resetPw_confirmation = null;
    }

    public function guardarReset(): void
    {
        $this->autorizar();
        $this->validate([
            'resetPw' => ['required', 'string', 'min:8', 'same:resetPw_confirmation'],
        ]);
        $u = User::findOrFail($this->resetId);
        $u->update(['password' => Hash::make($this->resetPw), 'password_cambiada_en' => null]);
        Log::info('admin.usuario.password_reseteada', ['actor' => auth()->id(), 'usuario' => $u->id]);
        $this->reset('resetId', 'resetPw', 'resetPw_confirmation');
        session()->flash('status', 'Contraseña restablecida.');
    }

    private function queryUsuarios(): Builder
    {
        return User::query()->with('roles')
            ->when($this->filtroRol, fn ($x) => $x->role($this->filtroRol))
            ->when($this->filtroEstado, fn ($x) => $x->where('activo', $this->filtroEstado === 'activo'))
            ->when($this->q, fn ($x) => $x->where(fn ($w) => $w
                ->where('name', 'like', "%{$this->q}%")->orWhere('email', 'like', "%{$this->q}%")))
            ->orderBy($this->campoOrdenValido(self::COLUMNAS_ORDENABLES) ?? 'name', $this->ordenDir);
    }

    public function exportar(): StreamedResponse
    {
        $usuarios = $this->queryUsuarios()->get();

        return $this->descargarExcel('usuarios.xlsx', [
            'Nombre', 'Correo', 'Roles', 'Estado',
        ], $usuarios->map(fn (User $u) => [
            $u->name, $u->email, $u->getRoleNames()->implode(', '), $u->activo ? 'Activo' : 'Inactivo',
        ]));
    }

    public function with(): array
    {
        return ['usuarios' => $this->queryUsuarios()->paginate(15), 'roles' => Role::orderBy('name')->pluck('name')];
    }
}; ?>

<x-ui.page title="Usuarios" subtitle="Altas, roles y estado de acceso del equipo.">
    <x-slot:actions>
        <button wire:click="exportar" class="btn-secondary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            Exportar
        </button>
        <button wire:click="nuevo" class="btn-primary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            Nuevo usuario
        </button>
    </x-slot:actions>

    @if ($mostrandoForm)
        <x-ui.card :title="$editandoId ? 'Editar usuario' : 'Nuevo usuario'">
            <form wire:submit="guardar" class="grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="lbl">Nombre</label>
                    <input wire:model="nombre" class="field w-full">
                    @error('nombre') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Correo</label>
                    <input wire:model="correo" type="email" class="field w-full">
                    @error('correo') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Contraseña @if ($editandoId)<span class="normal-case text-ink-secondary dark:text-gray-500">(en blanco = no cambiar)</span>@endif</label>
                    <input wire:model="clave" type="password" class="field w-full">
                    @error('clave') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="lbl">Confirmar contraseña</label>
                    <input wire:model="clave_confirmation" type="password" class="field w-full">
                </div>
                <div class="sm:col-span-2">
                    <label class="lbl">Roles</label>
                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                        @foreach ($roles as $r)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="rolesSel" value="{{ $r }}" class="rounded border-line dark:border-white/10 text-brand-600 focus:ring-brand-500"> {{ $r }}
                            </label>
                        @endforeach
                    </div>
                    @error('rolesSel') <span class="mt-1 block text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                </div>
                <div class="flex gap-3 sm:col-span-2">
                    <button class="btn-primary">Guardar</button>
                    <button type="button" wire:click="$set('mostrandoForm', false)" class="btn-secondary">Cancelar</button>
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card class="p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
                <label class="lbl">Buscar</label>
                <input wire:model.live.debounce.400ms="q" placeholder="Nombre o correo" class="field-sm w-full">
            </div>
            <div>
                <label class="lbl">Rol</label>
                <select wire:model.live="filtroRol" class="field-sm w-full">
                    <option value="">Todos</option>
                    @foreach ($roles as $r) <option value="{{ $r }}">{{ $r }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="lbl">Estado</label>
                <select wire:model.live="filtroEstado" class="field-sm w-full">
                    <option value="">Todos</option>
                    <option value="activo">Activos</option>
                    <option value="inactivo">Inactivos</option>
                </select>
            </div>
        </div>
    </x-ui.card>

    <x-ui.card flush>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                <tr>
                    <x-ui.th-sort campo="name" :actual="$ordenCampo" :dir="$ordenDir">Nombre</x-ui.th-sort>
                    <x-ui.th-sort campo="email" :actual="$ordenCampo" :dir="$ordenDir">Correo</x-ui.th-sort>
                    <th>Roles</th>
                    <x-ui.th-sort campo="activo" :actual="$ordenCampo" :dir="$ordenDir">Estado</x-ui.th-sort>
                    <th class="!text-right">Acciones</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($usuarios as $u)
                    <tr wire:key="u-{{ $u->id }}">
                        <td class="font-medium text-ink-900 dark:text-gray-100">{{ $u->name }}</td>
                        <td class="text-ink-secondary dark:text-gray-400">{{ $u->email }}</td>
                        <td>
                            @foreach ($u->getRoleNames() as $rn)
                                <x-ui.badge color="indigo" class="mr-1">{{ $rn }}</x-ui.badge>
                            @endforeach
                        </td>
                        <td><x-ui.badge :color="$u->activo ? 'green' : 'gray'">{{ $u->activo ? 'Activo' : 'Inactivo' }}</x-ui.badge></td>
                        <td class="num whitespace-nowrap">
                            <div class="inline-flex items-center gap-3">
                                <button wire:click="editar({{ $u->id }})" class="btn-link">Editar</button>
                                <button wire:click="toggleActivo({{ $u->id }})" class="btn-link !text-ink-secondary dark:!text-gray-400">{{ $u->activo ? 'Desactivar' : 'Activar' }}</button>
                                <button wire:click="pedirReset({{ $u->id }})" class="btn-link !text-ink-secondary dark:!text-gray-400">Clave</button>
                                <button type="button" x-data x-on:click="Swal.fire({
                                            title: '¿Eliminar usuario?',
                                            text: @js('¿Eliminar a '.$u->name.'?'),
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonText: 'Eliminar',
                                            cancelButtonText: 'Cancelar',
                                            confirmButtonColor: '#7A1E1E',
                                            reverseButtons: true,
                                        }).then((r) => { if (r.isConfirmed) $wire.eliminar({{ $u->id }}) })" class="btn-danger-link">Eliminar</button>
                            </div>
                        </td>
                    </tr>
                    @if ($resetId === $u->id)
                        <tr class="bg-brand-50/40 dark:bg-brand-900/10" wire:key="reset-{{ $u->id }}">
                            <td colspan="5">
                                <form wire:submit="guardarReset" class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm text-ink-900 dark:text-gray-300">Nueva contraseña para <b>{{ $u->name }}</b>:</span>
                                    <input wire:model="resetPw" type="password" class="field-sm">
                                    <input wire:model="resetPw_confirmation" type="password" placeholder="Confirmar" class="field-sm">
                                    <button class="btn-dark">Restablecer</button>
                                    <button type="button" wire:click="$set('resetId', null)" class="btn-link !text-ink-secondary dark:!text-gray-400">Cancelar</button>
                                    @error('resetPw') <span class="text-xs text-danger dark:text-danger-dark">{{ $message }}</span> @enderror
                                </form>
                            </td>
                        </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <div>{{ $usuarios->links() }}</div>
</x-ui.page>
