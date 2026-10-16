<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use App\Support\Permisos;
use App\Support\RolesBase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

trait CreaUsuarios
{
    protected function crearPermisos(): void
    {
        foreach (Permisos::TODOS as $p) {
            Permission::findOrCreate($p, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @param  list<string>  $permisos */
    protected function usuarioCon(array $permisos = [], bool $activo = true): User
    {
        $this->crearPermisos();

        $user = User::factory()->create(['activo' => $activo]);

        if ($permisos !== []) {
            $rol = Role::findOrCreate('rol_'.uniqid(), 'web');
            $rol->syncPermissions($permisos);
            $user->assignRole($rol);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    protected function admin(): User
    {
        $this->crearPermisos();
        $rol = Role::findOrCreate(RolesBase::ADMINISTRADOR, 'web');
        $rol->forceFill(['protegido' => true])->save();
        $rol->syncPermissions(Permisos::TODOS);

        $user = User::factory()->create(['activo' => true]);
        $user->assignRole($rol);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }
}
