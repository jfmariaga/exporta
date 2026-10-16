<?php

use App\Models\Role;
use App\Support\Permisos;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Permission;

new #[Layout('layouts.app')] class extends Component
{
    public function with(): array
    {
        $roles = Role::with('permissions')->orderBy('name')->get();

        $matriz = [];
        foreach (Permisos::TODOS as $permiso) {
            $matriz[$permiso] = $roles->filter(fn (Role $r) => $r->permissions->contains('name', $permiso))->pluck('name')->all();
        }

        return [
            'matriz' => $matriz,
            'etiquetas' => Permisos::etiquetas(),
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
                <tr><th>Permiso</th><th>Descripción</th><th>Roles que lo tienen</th></tr>
                </thead>
                <tbody>
                @foreach ($matriz as $permiso => $rolesConPermiso)
                    <tr wire:key="p-{{ $permiso }}">
                        <td><span class="rounded bg-ink-100 px-1.5 py-0.5 font-mono text-xs text-ink-600">{{ $permiso }}</span></td>
                        <td class="text-ink-700">{{ $etiquetas[$permiso] ?? '' }}</td>
                        <td>
                            @forelse ($rolesConPermiso as $rn)
                                <x-ui.badge color="indigo" class="mr-1">{{ $rn }}</x-ui.badge>
                            @empty
                                <span class="text-ink-400">—</span>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>
</x-ui.page>
