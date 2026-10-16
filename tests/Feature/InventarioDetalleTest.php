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

    public function test_lista_filas_sin_totalizar(): void
    {
        $this->sembrar();

        Volt::actingAs($this->usuarioCon([Permisos::SEGUIMIENTO_EDITAR]))
            ->test('inventario.detalle')
            ->assertOk()
            ->assertSee('062139')
            ->assertSee('50070753-275')
            ->assertSee('062029')
            ->assertSee('50070931-288');
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
