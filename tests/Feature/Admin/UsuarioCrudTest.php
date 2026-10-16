<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Support\Permisos;
use App\Support\RolesBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\CreaUsuarios;
use Tests\TestCase;

class UsuarioCrudTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    private function rolesBase(): void
    {
        $this->crearPermisos();
        foreach (RolesBase::permisos() as $nombre => $permisos) {
            Role::findOrCreate($nombre, 'web')->syncPermissions($permisos);
        }
    }

    public function test_crea_usuario_con_rol(): void
    {
        $this->rolesBase();

        Volt::actingAs($this->admin())
            ->test('admin.usuarios.index')
            ->call('nuevo')
            ->set('nombre', 'Planeador 1')
            ->set('correo', 'p1@levapan.com')
            ->set('clave', 'secret123')
            ->set('clave_confirmation', 'secret123')
            ->set('rolesSel', [RolesBase::PLANEADOR])
            ->call('guardar')
            ->assertHasNoErrors();

        $u = User::where('email', 'p1@levapan.com')->first();
        $this->assertTrue($u->activo);
        $this->assertTrue($u->can(Permisos::SEGUIMIENTO_EDITAR));
        $this->assertFalse($u->can(Permisos::ADMIN_USUARIOS));
    }

    public function test_email_unico(): void
    {
        $this->rolesBase();
        User::factory()->create(['email' => 'dup@levapan.com']);

        Volt::actingAs($this->admin())
            ->test('admin.usuarios.index')
            ->call('nuevo')
            ->set('nombre', 'X')
            ->set('correo', 'dup@levapan.com')
            ->set('clave', 'secret123')
            ->set('clave_confirmation', 'secret123')
            ->set('rolesSel', [RolesBase::CONSULTA])
            ->call('guardar')
            ->assertHasErrors('correo');
    }

    public function test_reset_password(): void
    {
        $this->rolesBase();
        $u = User::factory()->create(['password' => Hash::make('vieja123')]);

        Volt::actingAs($this->admin())
            ->test('admin.usuarios.index')
            ->call('pedirReset', $u->id)
            ->set('resetPw', 'nueva123')
            ->set('resetPw_confirmation', 'nueva123')
            ->call('guardarReset')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('nueva123', $u->fresh()->password));
    }

    public function test_toggle_activo(): void
    {
        $this->rolesBase();
        $u = User::factory()->create(['activo' => true]);

        Volt::actingAs($this->admin())
            ->test('admin.usuarios.index')
            ->call('toggleActivo', $u->id);

        $this->assertFalse($u->fresh()->activo);
    }

    public function test_sin_permiso_recibe_403(): void
    {
        $this->actingAs($this->usuarioCon([Permisos::PEDIDOS_VER]))
            ->get('/admin/usuarios')->assertForbidden();
    }
}
