<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\RolesBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\CreaUsuarios;
use Tests\TestCase;

class UltimoAdministradorTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    public function test_no_se_puede_desactivar_al_unico_administrador(): void
    {
        $admin = $this->admin();

        Volt::actingAs($admin)
            ->test('admin.usuarios.index')
            ->call('toggleActivo', $admin->id)
            ->assertHasErrors();

        $this->assertTrue($admin->fresh()->activo);
    }

    public function test_no_se_puede_quitar_el_rol_al_unico_administrador(): void
    {
        $admin = $this->admin();

        Volt::actingAs($admin)
            ->test('admin.usuarios.index')
            ->call('editar', $admin->id)
            ->set('rolesSel', [RolesBase::CONSULTA])
            ->call('guardar')
            ->assertHasErrors();

        $this->assertTrue($admin->fresh()->hasRole(RolesBase::ADMINISTRADOR));
    }

    public function test_con_dos_administradores_si_se_puede_desactivar_uno(): void
    {
        $admin1 = $this->admin();
        $admin2 = User::factory()->create(['activo' => true]);
        $admin2->assignRole(RolesBase::ADMINISTRADOR);

        Volt::actingAs($admin1)
            ->test('admin.usuarios.index')
            ->call('toggleActivo', $admin2->id)
            ->assertHasNoErrors();

        $this->assertFalse($admin2->fresh()->activo);
    }
}
