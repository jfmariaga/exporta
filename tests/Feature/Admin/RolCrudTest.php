<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Support\Permisos;
use App\Support\RolesBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\CreaUsuarios;
use Tests\TestCase;

class RolCrudTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    public function test_crea_rol_con_permisos(): void
    {
        Volt::actingAs($this->admin())
            ->test('admin.roles.index')
            ->call('nuevo')
            ->set('nombre', 'Coordinador')
            ->set('permisosSel', [Permisos::PEDIDOS_VER, Permisos::CORREO_GENERAR])
            ->call('guardar')
            ->assertHasNoErrors();

        $rol = Role::findByName('Coordinador');
        $this->assertEqualsCanonicalizing(
            [Permisos::PEDIDOS_VER, Permisos::CORREO_GENERAR],
            $rol->permissions->pluck('name')->all(),
        );
    }

    public function test_no_se_puede_eliminar_el_rol_administrador(): void
    {
        $admin = $this->admin();
        $rolAdmin = Role::findByName(RolesBase::ADMINISTRADOR);

        Volt::actingAs($admin)
            ->test('admin.roles.index')
            ->call('eliminar', $rolAdmin->id)
            ->assertStatus(422);

        $this->assertNotNull(Role::find($rolAdmin->id));
    }

    public function test_no_se_puede_eliminar_rol_con_usuarios(): void
    {
        $admin = $this->admin();
        $rol = Role::findOrCreate('EnUso', 'web');
        $this->usuarioCon([])->assignRole($rol);

        Volt::actingAs($admin)
            ->test('admin.roles.index')
            ->call('eliminar', $rol->id)
            ->assertStatus(422);

        $this->assertNotNull(Role::find($rol->id));
    }

    public function test_pantalla_de_permisos_es_solo_lectura(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/permisos')
            ->assertOk()
            ->assertSee(Permisos::SEGUIMIENTO_EDITAR)
            ->assertDontSee('wire:model="permisosSel"', false);
    }

    public function test_sin_permiso_recibe_403(): void
    {
        $this->actingAs($this->usuarioCon([Permisos::PEDIDOS_VER]))
            ->get('/admin/roles')->assertForbidden();
    }
}
