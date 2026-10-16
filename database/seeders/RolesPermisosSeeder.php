<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Support\Permisos;
use App\Support\RolesBase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Siembra el catálogo fijo de permisos, los roles base y un usuario
 * administrador inicial. Idempotente: re-ejecutar no crea duplicados.
 */
class RolesPermisosSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permisos::TODOS as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        foreach (RolesBase::permisos() as $nombreRol => $permisos) {
            $rol = Role::findOrCreate($nombreRol, 'web');
            $rol->syncPermissions($permisos);

            if ($nombreRol === RolesBase::ADMINISTRADOR && ! $rol->protegido) {
                $rol->forceFill(['protegido' => true])->save();
            }
        }

        $email = env('ADMIN_EMAIL', 'admin@example.com');

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrador',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'cambiar123')),
                'activo' => true,
            ],
        );

        if (! $admin->hasRole(RolesBase::ADMINISTRADOR)) {
            $admin->assignRole(RolesBase::ADMINISTRADOR);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
