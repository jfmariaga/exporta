<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\LineaPedido;
use App\Models\Seguimiento;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\CreaUsuarios;
use Tests\TestCase;

class GestionPorItemTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    private function linea(string $pedido, string $item): void
    {
        LineaPedido::create([
            'pedido' => $pedido, 'item' => $item, 'bodega' => '004', 'unidad_medida' => 'UN',
            'cantidad_pedida' => 100, 'nit_cliente' => 'B66061078', 'nombre_cliente' => 'DISTRIBAR',
            'estado' => 'PL', 'activo' => true, 'descripcion' => "DESC {$item}",
        ]);
    }

    public function test_sin_estado_seleccionado_no_muestra_tabla(): void
    {
        Cliente::create(['nit_cliente' => 'B66061078', 'cliente' => 'DISTRIBAR', 'destino' => 'ESPAÑA']);
        $this->linea('179', '001131');
        Seguimiento::create(['pedido' => '179', 'item' => '001131', 'estado_materiales' => 'OK']);

        Volt::actingAs($this->usuarioCon([Permisos::PEDIDOS_VER]))
            ->test('materiales.index')
            ->assertOk()
            ->assertDontSee('001131');
    }

    public function test_filtra_por_estado_y_omite_items_sin_estado(): void
    {
        Cliente::create(['nit_cliente' => 'B66061078', 'cliente' => 'DISTRIBAR', 'destino' => 'ESPAÑA']);

        $this->linea('179', '001131');
        Seguimiento::create(['pedido' => '179', 'item' => '001131', 'estado_materiales' => 'OK']);

        $this->linea('179', '001200');
        Seguimiento::create(['pedido' => '179', 'item' => '001200', 'estado_materiales' => 'EN INVENTARIO']);

        // Línea sin ningún seguimiento (sin estado asignado): nunca debe aparecer.
        $this->linea('179', '001300');

        $comp = Volt::actingAs($this->usuarioCon([Permisos::PEDIDOS_VER]))
            ->test('materiales.index')
            ->set('estado', 'OK');

        $comp->assertSee('001131')->assertDontSee('001200')->assertDontSee('001300');

        $comp->set('estado', 'EN INVENTARIO')
            ->assertSee('001200')->assertDontSee('001131')->assertDontSee('001300');
    }

    public function test_requiere_permiso_pedidos_ver(): void
    {
        $this->actingAs($this->usuarioCon([]))->get('/materiales')->assertForbidden();
    }
}
