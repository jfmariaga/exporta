<?php

namespace Tests\Feature;

use App\Models\InventarioSincboxDetalle;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\CreaUsuarios;
use Tests\TestCase;

class InventarioDetalleTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    private function sembrar(): void
    {
        InventarioSincboxDetalle::create([
            'item' => '062139', 'warehouse' => '004', 'ubication' => '02J0404A',
            'descripcion' => 'SALSA AJI', 'lote' => '50070753-275', 'product_id' => 1,
            'cantidad' => 4896, 'bloqueado' => false, 'creado' => '2026-10-02',
        ]);
        InventarioSincboxDetalle::create([
            'item' => '062029', 'warehouse' => '004', 'ubication' => '02I0105D',
            'descripcion' => 'MAQ-SALSA', 'lote' => '50070931-288', 'product_id' => 2,
            'cantidad' => 3360, 'bloqueado' => true, 'creado' => '2026-09-15',
        ]);
    }

    public function test_pagina_requiere_permiso(): void
    {
        $this->sembrar();

        $this->actingAs($this->usuarioCon([Permisos::PEDIDOS_VER]))->get('/inventario/detalle')->assertForbidden();
        $this->actingAs($this->usuarioCon([Permisos::SEGUIMIENTO_EDITAR]))->get('/inventario/detalle')->assertOk();
    }

    public function test_lista_agrupada_por_item_y_lote(): void
    {
        $this->sembrar();

        Volt::actingAs($this->usuarioCon([Permisos::SEGUIMIENTO_EDITAR]))
            ->test('inventario.detalle')
            ->assertOk()
            ->assertSee('062139')
            ->assertSee('50070753-275')
            ->assertSee('062029')
            ->assertSee('50070931-288')
            // La ubicación (campo por bodega) ya no se muestra en esta tabla.
            ->assertDontSee('02J0404A')
            ->assertDontSee('02I0105D');
    }

    public function test_suma_la_cantidad_de_distintas_ubicaciones_del_mismo_lote(): void
    {
        InventarioSincboxDetalle::create([
            'item' => '064025', 'warehouse' => '004', 'ubication' => '02J1001C',
            'descripcion' => 'MERM DP PIÑA', 'lote' => '50070777-278', 'product_id' => 3,
            'cantidad' => 3120, 'bloqueado' => false, 'creado' => '2026-10-16',
        ]);
        InventarioSincboxDetalle::create([
            'item' => '064025', 'warehouse' => '004', 'ubication' => '02J0305B',
            'descripcion' => 'MERM DP PIÑA', 'lote' => '50070777-278', 'product_id' => 3,
            'cantidad' => 2400, 'bloqueado' => false, 'creado' => '2026-10-16',
        ]);
        InventarioSincboxDetalle::create([
            'item' => '064025', 'warehouse' => '004', 'ubication' => '02I0101C',
            'descripcion' => 'MERM DP PIÑA', 'lote' => '50070777-280', 'product_id' => 3,
            'cantidad' => 2880, 'bloqueado' => false, 'creado' => '2026-10-16',
        ]);

        $comp = Volt::actingAs($this->usuarioCon([Permisos::SEGUIMIENTO_EDITAR]))
            ->test('inventario.detalle')
            ->set('item', '064025');

        $filas = $comp->viewData('filas');
        $this->assertSame(2, $filas->total());

        $lote278 = $filas->firstWhere('lote', '50070777-278');
        $this->assertEquals(5520, (float) $lote278->cantidad);

        $lote280 = $filas->firstWhere('lote', '50070777-280');
        $this->assertEquals(2880, (float) $lote280->cantidad);
    }

    public function test_filtra_por_item_y_lote(): void
    {
        $this->sembrar();

        Volt::actingAs($this->usuarioCon([Permisos::SEGUIMIENTO_EDITAR]))
            ->test('inventario.detalle')
            ->set('item', '062139')
            ->assertSee('062139')
            ->assertDontSee('062029');
    }

    public function test_filtra_por_rango_de_fecha_de_creacion(): void
    {
        $this->sembrar();

        Volt::actingAs($this->usuarioCon([Permisos::SEGUIMIENTO_EDITAR]))
            ->test('inventario.detalle')
            ->set('desde', '2026-10-01')
            ->assertSee('062139')
            ->assertDontSee('062029');
    }
}
