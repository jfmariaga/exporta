<?php

namespace Tests\Feature\Admin;

use App\Models\ParametroItem;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\CreaUsuarios;
use Tests\TestCase;

class ParametroItemCrudTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    public function test_crea_parametro(): void
    {
        Volt::actingAs($this->admin())
            ->test('admin.parametros-item.index')
            ->call('nuevo')
            ->set('item', '001131')
            ->set('descripcion', 'ESENC COCO LEVAPAN 24X500cm3 P')
            ->set('factor_kg', '0.549125')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('parametros_item', ['item' => '001131', 'factor_kg' => '0.549125']);
    }

    public function test_no_permite_item_duplicado(): void
    {
        ParametroItem::create(['item' => '001131', 'factor_kg' => 0.5]);

        Volt::actingAs($this->admin())
            ->test('admin.parametros-item.index')
            ->call('nuevo')
            ->set('item', '001131')
            ->set('factor_kg', '0.6')
            ->call('guardar')
            ->assertHasErrors('item');
    }

    public function test_edita_y_elimina_parametro(): void
    {
        $p = ParametroItem::create(['item' => '001131', 'factor_kg' => 0.5]);

        Volt::actingAs($this->admin())
            ->test('admin.parametros-item.index')
            ->call('editar', $p->id)
            ->set('factor_kg', '0.75')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('parametros_item', ['id' => $p->id, 'factor_kg' => '0.750000']);

        Volt::actingAs($this->admin())
            ->test('admin.parametros-item.index')
            ->call('eliminar', $p->id);

        $this->assertDatabaseMissing('parametros_item', ['id' => $p->id]);
    }

    public function test_sin_permiso_recibe_403(): void
    {
        $this->actingAs($this->usuarioCon([Permisos::PEDIDOS_VER]))
            ->get('/admin/parametros-item')->assertForbidden();
    }
}
