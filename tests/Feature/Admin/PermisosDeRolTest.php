<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\CreaUsuarios;
use Tests\TestCase;

class PermisosDeRolTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    public function test_usuario_nuevo_hereda_exactamente_los_permisos_del_rol(): void
    {
        $this->crearPermisos();
        $rol = Role::findOrCreate('Consulta', 'web');
        $rol->syncPermissions([Permisos::PEDIDOS_VER]);

        $u = User::factory()->create();
        $u->assignRole($rol);

        $this->assertTrue($u->can(Permisos::PEDIDOS_VER));
        $this->assertFalse($u->can(Permisos::SEGUIMIENTO_EDITAR));
        $this->assertFalse($u->can(Permisos::CORREO_GENERAR));
    }

    public function test_cambio_de_permisos_del_rol_surte_efecto_sin_relogin(): void
    {
        $admin = $this->admin();
        $rol = Role::findOrCreate('Consulta', 'web');
        $rol->syncPermissions([Permisos::PEDIDOS_VER]);

        $consulta = User::factory()->create();
        $consulta->assignRole($rol);

        $this->actingAs($consulta)->get('/cargues/correo')->assertForbidden();

        Volt::actingAs($admin)
            ->test('admin.roles.index')
            ->call('editar', $rol->id)
            ->set('permisosSel', [Permisos::PEDIDOS_VER, Permisos::CORREO_GENERAR])
            ->call('guardar')
            ->assertHasNoErrors();

        $this->actingAs($consulta)->get('/cargues/correo')->assertOk();
    }
}
