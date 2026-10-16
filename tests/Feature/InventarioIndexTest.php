<?php

namespace Tests\Feature;

use App\Models\InventarioSincbox;
use App\Models\LineaPedido;
use App\Models\Seguimiento;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\CreaUsuarios;
use Tests\TestCase;

class InventarioIndexTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    public function test_pagina_inventario_requiere_permiso(): void
    {
        $this->actingAs($this->usuarioCon([Permisos::PEDIDOS_VER]))->get('/inventario')->assertForbidden();
        $this->actingAs($this->usuarioCon([Permisos::SEGUIMIENTO_EDITAR]))->get('/inventario')->assertOk();
    }

    public function test_pagina_inventario_renderiza_estados(): void
    {
        InventarioSincbox::create(['item' => '001131', 'cantidad_total' => 500]);
        LineaPedido::create([
            'pedido' => '179', 'item' => '001131', 'bodega' => '004', 'unidad_medida' => 'UN',
            'cantidad_pedida' => 300, 'nit_cliente' => 'B66061078', 'nombre_cliente' => 'DISTRIBAR',
            'estado' => 'PL', 'activo' => true, 'descripcion' => 'DESC 001131',
        ]);
        Seguimiento::create(['pedido' => '179', 'item' => '001131', 'cantidad' => 300, 'inventario' => 200]);

        Volt::actingAs($this->usuarioCon([Permisos::SEGUIMIENTO_EDITAR]))
            ->test('inventario.index')
            ->assertOk()
            ->assertSee('001131')
            ->assertSee('en reparto');
    }

    public function test_boton_sincronizar_inventario_requiere_permiso_erp_importar(): void
    {
        Volt::actingAs($this->usuarioCon([Permisos::SEGUIMIENTO_EDITAR]))
            ->test('inventario.index')
            ->call('sincronizar')
            ->assertStatus(403);
    }
}
