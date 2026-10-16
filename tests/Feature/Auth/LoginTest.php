<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(bool $activo = true): User
    {
        Permission::findOrCreate(Permisos::PEDIDOS_VER, 'web');
        $rol = Role::findOrCreate('Consulta', 'web');
        $rol->givePermissionTo(Permisos::PEDIDOS_VER);

        $u = User::factory()->create([
            'email' => 'planeador@levapan.com',
            'password' => Hash::make('secret123'),
            'activo' => $activo,
        ]);
        $u->assignRole($rol);

        return $u;
    }

    public function test_pantalla_de_login_se_renderiza(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_login_correcto(): void
    {
        $this->usuario();

        Volt::test('pages.auth.login')
            ->set('form.email', 'planeador@levapan.com')
            ->set('form.password', 'secret123')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_clave_incorrecta(): void
    {
        $this->usuario();

        Volt::test('pages.auth.login')
            ->set('form.email', 'planeador@levapan.com')
            ->set('form.password', 'mala')
            ->call('login')
            ->assertHasErrors('form.email');

        $this->assertGuest();
    }

    public function test_usuario_inactivo_no_entra(): void
    {
        $this->usuario(activo: false);

        Volt::test('pages.auth.login')
            ->set('form.email', 'planeador@levapan.com')
            ->set('form.password', 'secret123')
            ->call('login')
            ->assertHasErrors('form.email');

        $this->assertGuest();
    }

    public function test_ruta_protegida_sin_sesion_redirige_al_login(): void
    {
        $this->get('/pedidos-liberar')->assertRedirect('/login');
    }

    public function test_no_existe_registro_publico(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_usuario_desactivado_en_caliente_es_deslogueado(): void
    {
        $u = $this->usuario();
        $this->actingAs($u);
        $u->update(['activo' => false]);

        $this->get('/pedidos-liberar')->assertRedirect('/login');
        $this->assertGuest();
    }
}
