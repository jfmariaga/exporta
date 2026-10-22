<?php

namespace Tests\Feature\Admin;

use App\Models\Cliente;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\CreaUsuarios;
use Tests\TestCase;

class ClienteCrudTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    public function test_crea_cliente(): void
    {
        Volt::actingAs($this->admin())
            ->test('admin.clientes.index')
            ->call('nuevo')
            ->set('nit_cliente', 'B66061078')
            ->set('cliente', 'DISTRIBAR DISTRIBUCION HOSTELERIA SL')
            ->set('destino', 'ESPAÑA')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('clientes', ['nit_cliente' => 'B66061078', 'destino' => 'ESPAÑA']);
    }

    public function test_no_permite_nit_duplicado(): void
    {
        Cliente::create(['nit_cliente' => 'B66061078', 'cliente' => 'X', 'destino' => 'ESPAÑA']);

        Volt::actingAs($this->admin())
            ->test('admin.clientes.index')
            ->call('nuevo')
            ->set('nit_cliente', 'B66061078')
            ->set('cliente', 'OTRO')
            ->call('guardar')
            ->assertHasErrors('nit_cliente');
    }

    public function test_edita_y_elimina_cliente(): void
    {
        $c = Cliente::create(['nit_cliente' => 'B66061078', 'cliente' => 'X', 'destino' => 'ESPAÑA']);

        Volt::actingAs($this->admin())
            ->test('admin.clientes.index')
            ->call('editar', $c->id)
            ->set('destino', 'FRANCIA')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('clientes', ['id' => $c->id, 'destino' => 'FRANCIA']);

        Volt::actingAs($this->admin())
            ->test('admin.clientes.index')
            ->call('eliminar', $c->id);

        $this->assertDatabaseMissing('clientes', ['id' => $c->id]);
    }

    public function test_sin_permiso_recibe_403(): void
    {
        $this->actingAs($this->usuarioCon([Permisos::PEDIDOS_VER]))
            ->get('/admin/clientes')->assertForbidden();
    }
}
