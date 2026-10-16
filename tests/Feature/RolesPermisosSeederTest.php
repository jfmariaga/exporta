<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Permisos;
use App\Support\RolesBase;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RolesPermisosSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_siembra_permisos_roles_y_admin(): void
    {
        $this->seed(RolesPermisosSeeder::class);

        $this->assertSame(6, Permission::count());
        $this->assertEqualsCanonicalizing(Permisos::TODOS, Permission::pluck('name')->all());

        $this->assertEqualsCanonicalizing(
            [RolesBase::ADMINISTRADOR, RolesBase::PLANEADOR, RolesBase::CONSULTA],
            Role::pluck('name')->all(),
        );

        $planeador = Role::findByName(RolesBase::PLANEADOR);
        $this->assertEqualsCanonicalizing(
            [Permisos::PEDIDOS_VER, Permisos::SEGUIMIENTO_EDITAR, Permisos::CORREO_GENERAR],
            $planeador->permissions->pluck('name')->all(),
        );

        $this->assertTrue(Role::findByName(RolesBase::ADMINISTRADOR)->protegido);

        $admin = User::where('email', env('ADMIN_EMAIL', 'admin@example.com'))->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole(RolesBase::ADMINISTRADOR));
    }

    public function test_es_idempotente(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $this->seed(RolesPermisosSeeder::class);

        $this->assertSame(6, Permission::count());
        $this->assertSame(3, Role::count());
        $this->assertSame(1, User::count());
    }
}
