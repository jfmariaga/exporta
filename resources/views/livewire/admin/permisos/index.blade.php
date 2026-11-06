<?php

use App\Models\Role;
use App\Support\ConOrdenamiento;
use App\Support\Permisos;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Permission;

new #[Layout('layouts.app')] class extends Component
{
    use ConOrdenamiento;

    private const COLUMNAS_ORDENABLES = ['permiso', 'descripcion', 'roles_count'];

    public function with(): array
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        $etiquetas = Permisos::etiquetas();

        $matriz = collect(Permisos::TODOS)->map(fn (string $permiso) => [
            'permiso' => $permiso,
            'descripcion' => $etiquetas[$permiso] ?? '',
            'roles' => $roles->filter(fn (Role $r) => $r->permissions->contains('name', $permiso))->pluck('name')->all(),
        ])->map(fn (array $fila) => $fila + ['roles_count' => count($fila['roles'])]);

        if ($campo = $this->campoOrdenValido(self::COLUMNAS_ORDENABLES)) {
            $matriz = $matriz->sortBy($campo, SORT_REGULAR, $this->ordenDir === 'desc')->values();
        }

        return [
            'matriz' => $matriz,
            'totalPermisos' => Permission::count(),
        ];
    }
}; ?>

<x-ui.page title="Catálogo de permisos"
           subtitle="Fijo ({{ $totalPermisos }} permisos), sólo lectura. Los roles se administran en la sección Roles.">
    <x-slot:actions>
        <a href="{{ route('admin.roles.index') }}" wire:navigate class="btn-secondary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Roles
        </a>
    </x-slot:actions>

    <x-ui.card flush>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                <tr>
                    <x-ui.th-sort campo="permiso" :actual="$ordenCampo" :dir="$ordenDir">Permiso</x-ui.th-sort>
                    <x-ui.th-sort campo="descripcion" :actual="$ordenCampo" :dir="$ordenDir">Descripción</x-ui.th-sort>
                    <x-ui.th-sort campo="roles_count" :actual="$ordenCampo" :dir="$ordenDir">Roles que lo tienen</x-ui.th-sort>
                </tr>
                </thead>
                <tbody>
                @foreach ($matriz as $fila)
                    <tr wire:key="p-{{ $fila['permiso'] }}">
                        <td><span class="rounded bg-surface-alt dark:bg-white/5 px-1.5 py-0.5 font-mono text-xs text-ink-secondary dark:text-gray-400">{{ $fila['permiso'] }}</span></td>
                        <td class="text-ink-900 dark:text-gray-300">{{ $fila['descripcion'] }}</td>
                        <td>
                            @forelse ($fila['roles'] as $rn)
                                <x-ui.badge color="indigo" class="mr-1">{{ $rn }}</x-ui.badge>
                            @empty
                                <span class="text-ink-secondary dark:text-gray-500">—</span>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>
</x-ui.page>
