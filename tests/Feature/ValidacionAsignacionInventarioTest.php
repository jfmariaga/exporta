<?php

namespace Tests\Feature;

use App\Models\InventarioSincbox;
use App\Models\LineaPedido;
use App\Models\Seguimiento;
use App\Services\Inventario\DisponibilidadInventario;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\CreaUsuarios;
use Tests\TestCase;

class ValidacionAsignacionInventarioTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    private function linea(string $pedido, string $item, float $cant = 100): void
    {
        LineaPedido::create([
            'pedido' => $pedido, 'item' => $item, 'bodega' => '004', 'unidad_medida' => 'UN',
            'cantidad_pedida' => $cant, 'nit_cliente' => 'B66061078', 'nombre_cliente' => 'DISTRIBAR',
            'estado' => 'PL', 'activo' => true, 'descripcion' => "DESC {$item}",
        ]);
    }

    public function test_rechaza_asignacion_que_excede_el_disponible_compartido_entre_pedidos(): void
    {
        InventarioSincbox::create(['item' => '001131', 'cantidad_total' => 100]);
        $this->linea('A', '001131', 200);
        Seguimiento::create(['pedido' => 'A', 'item' => '001131', 'cantidad' => 200, 'inventario' => 80]);
        $this->linea('B', '001131', 200);

        $user = $this->usuarioCon([Permisos::PEDIDOS_VER, Permisos::SEGUIMIENTO_EDITAR]);

        Volt::actingAs($user)
            ->test('pedidos.show', ['pedido' => 'B'])
            ->set('lineasSeg.001131.inventario', '30')
            ->call('guardarLineas')
            ->assertHasErrors('lineasSeg.001131.inventario');

        $this->assertTrue(DB::table('seguimientos')->where(['pedido' => 'B', 'item' => '001131'])->doesntExist());
    }

    public function test_acepta_asignacion_dentro_del_limite(): void
    {
        InventarioSincbox::create(['item' => '001131', 'cantidad_total' => 100]);
        $this->linea('A', '001131', 200);
        Seguimiento::create(['pedido' => 'A', 'item' => '001131', 'cantidad' => 200, 'inventario' => 80]);
        $this->linea('B', '001131', 200);

        $user = $this->usuarioCon([Permisos::PEDIDOS_VER, Permisos::SEGUIMIENTO_EDITAR]);

        Volt::actingAs($user)
            ->test('pedidos.show', ['pedido' => 'B'])
            ->set('lineasSeg.001131.inventario', '20')
            ->call('guardarLineas')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('seguimientos', ['pedido' => 'B', 'item' => '001131', 'inventario' => 20]);
    }

    public function test_resalta_en_rojo_cuando_el_disponible_no_alcanza_lo_pedido(): void
    {
        // Pedido pide 200, sólo hay 100 sincronizados en todo el pool: insuficiente.
        InventarioSincbox::create(['item' => '001131', 'cantidad_total' => 100]);
        $this->linea('A', '001131', 200);

        $user = $this->usuarioCon([Permisos::PEDIDOS_VER, Permisos::SEGUIMIENTO_EDITAR]);

        Volt::actingAs($user)
            ->test('pedidos.show', ['pedido' => 'A'])
            ->assertSee('text-red-600', false);
    }

    public function test_no_resalta_cuando_el_disponible_cubre_lo_pedido(): void
    {
        InventarioSincbox::create(['item' => '001131', 'cantidad_total' => 500]);
        $this->linea('A', '001131', 200);

        $user = $this->usuarioCon([Permisos::PEDIDOS_VER, Permisos::SEGUIMIENTO_EDITAR]);

        Volt::actingAs($user)
            ->test('pedidos.show', ['pedido' => 'A'])
            ->assertDontSee('text-red-600', false);
    }

    public function test_disponible_mostrado_es_global_no_excluye_el_propio_pedido(): void
    {
        // Pool completo (100) ya asignado por entero al propio pedido 'A':
        // el "Disp." mostrado debe reflejar 0 (nada libre en el pool), no
        // volver a mostrar el total como si nada se hubiera asignado.
        InventarioSincbox::create(['item' => '001131', 'cantidad_total' => 100]);
        $this->linea('A', '001131', 100);
        Seguimiento::create(['pedido' => 'A', 'item' => '001131', 'cantidad' => 100, 'inventario' => 100]);

        $user = $this->usuarioCon([Permisos::PEDIDOS_VER, Permisos::SEGUIMIENTO_EDITAR]);

        Volt::actingAs($user)
            ->test('pedidos.show', ['pedido' => 'A'])
            ->assertSee('Disp.: 0');

        // Y sigue pudiendo guardar el mismo valor sin que el validador lo rechace.
        Volt::actingAs($user)
            ->test('pedidos.show', ['pedido' => 'A'])
            ->set('lineasSeg.001131.inventario', '100')
            ->call('guardarLineas')
            ->assertHasNoErrors();
    }

    public function test_estados_clasifica_sin_asignar_en_reparto_distribuido_y_sobre_asignado(): void
    {
        // Sin asignar: 50 sincronizados, nada repartido.
        InventarioSincbox::create(['item' => '001001', 'cantidad_total' => 50]);

        // Sobre-asignado: una sync posterior trajo menos de lo ya repartido.
        InventarioSincbox::create(['item' => '001002', 'cantidad_total' => 40]);
        $this->linea('C', '001002', 100);
        Seguimiento::create(['pedido' => 'C', 'item' => '001002', 'cantidad' => 100, 'inventario' => 60]);

        // Distribuido: el pool entero ya quedó asignado.
        InventarioSincbox::create(['item' => '001003', 'cantidad_total' => 30]);
        $this->linea('D', '001003', 100);
        Seguimiento::create(['pedido' => 'D', 'item' => '001003', 'cantidad' => 100, 'inventario' => 30]);

        // En reparto: ya se asignó parte, pero queda saldo libre.
        InventarioSincbox::create(['item' => '001004', 'cantidad_total' => 80]);
        $this->linea('E', '001004', 100);
        Seguimiento::create(['pedido' => 'E', 'item' => '001004', 'cantidad' => 100, 'inventario' => 20]);

        $estados = app(DisponibilidadInventario::class)->estados()->keyBy('item');

        $this->assertSame('sin_asignar', $estados['001001']['estado']);
        $this->assertEquals(50, $estados['001001']['disponible']);

        $this->assertSame('sobre_asignado', $estados['001002']['estado']);
        $this->assertEquals(-20, $estados['001002']['disponible']);

        $this->assertSame('distribuido', $estados['001003']['estado']);
        $this->assertEquals(0, $estados['001003']['disponible']);

        $this->assertSame('en_reparto', $estados['001004']['estado']);
        $this->assertEquals(60, $estados['001004']['disponible']);
    }

    public function test_calcula_total_pedido_y_faltante_contra_el_inventario_sincronizado(): void
    {
        // El total pedido es la demanda completa (PL/PP activos), sin
        // importar si ya se repartió inventario a esas líneas o no.
        InventarioSincbox::create(['item' => '001002', 'cantidad_total' => 40]);
        $this->linea('C', '001002', 100);
        Seguimiento::create(['pedido' => 'C', 'item' => '001002', 'cantidad' => 100, 'inventario' => 30]);
        $this->linea('F', '001002', 20);

        $estados = app(DisponibilidadInventario::class)->estados()->keyBy('item');

        $this->assertEquals(120, $estados['001002']['total_pedido']);
        $this->assertEquals(80, $estados['001002']['faltante']);
    }

    public function test_faltante_es_cero_cuando_el_inventario_cubre_el_pedido(): void
    {
        InventarioSincbox::create(['item' => '001005', 'cantidad_total' => 200]);
        $this->linea('G', '001005', 100);

        $estados = app(DisponibilidadInventario::class)->estados()->keyBy('item');

        $this->assertEquals(100, $estados['001005']['total_pedido']);
        $this->assertEquals(0, $estados['001005']['faltante']);
    }
}
