<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\LineaPedido;
use App\Models\ParametroItem;
use App\Models\Seguimiento;
use App\Models\SeguimientoPedido;
use App\Services\PedidosPorLiberarQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidosPorLiberarQueryTest extends TestCase
{
    use RefreshDatabase;

    private function sembrar(): void
    {
        Cliente::create(['nit_cliente' => 'B66061078', 'cliente' => 'DISTRIBAR', 'destino' => 'ESPAÑA']);
        ParametroItem::create(['item' => '001131', 'descripcion' => 'ESENC COCO', 'factor_kg' => 0.549125, 'categoria' => 'ESENCIAS Y VINAGRE']);
        ParametroItem::create(['item' => '030848', 'descripcion' => 'MAYONESA', 'factor_kg' => 0.2225, 'categoria' => 'DOY PACK']);

        $base = [
            'bodega' => '004', 'unidad_medida' => 'UN', 'cantidad_pedida' => 0,
            'nit_cliente' => 'B66061078', 'nombre_cliente' => 'DISTRIBAR', 'activo' => true,
        ];

        LineaPedido::create([...$base, 'pedido' => '179', 'item' => '001131', 'estado' => 'PL', 'cantidad_pedida' => 2400, 'fecha_elaboracion' => '2026-08-04']);
        LineaPedido::create([...$base, 'pedido' => '179', 'item' => '001200', 'estado' => 'PL', 'cantidad_pedida' => 100, 'fecha_elaboracion' => '2026-08-06']);
        LineaPedido::create([...$base, 'pedido' => '179', 'item' => '700010', 'estado' => 'PL', 'cantidad_pedida' => 5000, 'fecha_elaboracion' => '2026-08-01']);
        LineaPedido::create([...$base, 'pedido' => '180', 'item' => '030848', 'estado' => 'PP', 'cantidad_pedida' => 1000, 'nit_cliente' => '99999999', 'fecha_elaboracion' => '2026-08-10']);
        LineaPedido::create([...$base, 'pedido' => '181', 'item' => '001131', 'estado' => 'CO', 'cantidad_pedida' => 500, 'fecha_elaboracion' => '2026-07-01']);
    }

    public function test_solo_pl_pp_y_excluye_items_7x(): void
    {
        $this->sembrar();
        $pedidos = app(PedidosPorLiberarQuery::class)->resumenPorPedido()->getCollection()->pluck('pedido')->all();

        sort($pedidos);
        $this->assertSame(['179', '180'], $pedidos);
    }

    public function test_agregados_por_pedido(): void
    {
        $this->sembrar();
        $p179 = app(PedidosPorLiberarQuery::class)->resumenPorPedido()->getCollection()->firstWhere('pedido', '179');

        $this->assertEquals(2500, (int) $p179->cantidad_total);
        $this->assertStringStartsWith('2026-08-04', (string) $p179->fecha_solicitud);
        $this->assertSame('ESPAÑA', $p179->destino);
        $this->assertEqualsWithDelta(1.3179, (float) $p179->toneladas_total, 0.00001);
        $this->assertTrue((bool) $p179->toneladas_incompleto);
    }

    public function test_inventario_y_porcentaje_de_cumplimiento_por_pedido(): void
    {
        $this->sembrar();
        // inventario total 3000 ; cantidad total 2500 -> 3000/2500*100 = 120%
        Seguimiento::create(['pedido' => '179', 'item' => '001131', 'inventario' => 2500]);
        Seguimiento::create(['pedido' => '179', 'item' => '001200', 'inventario' => 500]);

        $p179 = app(PedidosPorLiberarQuery::class)->resumenPorPedido()->getCollection()->firstWhere('pedido', '179');

        $this->assertEquals(3000, (int) $p179->inventario_total);
        $this->assertEqualsWithDelta(120.0, (float) $p179->porcentaje_cumplimiento, 0.01);
    }

    public function test_seguimiento_de_pedido_y_estado_revision(): void
    {
        $this->sembrar();
        SeguimientoPedido::create(['pedido' => '179', 'estado_seguimiento' => 'Listo para cargar', 'fecha_tentativa_cargue' => '2026-08-26']);

        $col = app(PedidosPorLiberarQuery::class)->resumenPorPedido()->getCollection();
        $p179 = $col->firstWhere('pedido', '179');

        $this->assertSame('REVISADO', $p179->estado_revision);
        $this->assertSame('Listo para cargar', $p179->estado_seguimiento);
        $this->assertSame('✅', $p179->alerta);
        $this->assertSame('POR REVISAR', $col->firstWhere('pedido', '180')->estado_revision);
    }

    public function test_detalle_calcula_toneladas_y_porcentaje_por_linea(): void
    {
        $this->sembrar();
        Seguimiento::create(['pedido' => '179', 'item' => '001131', 'inventario' => 1200]);

        $detalle = app(PedidosPorLiberarQuery::class)->detallePedido('179');

        $linea = $detalle['lineas']->firstWhere('item', '001131');
        $this->assertEqualsWithDelta(1.3179, (float) $linea->toneladas_linea, 0.00001);
        // inventario / cantidad * 100 -> 1200 / 2400 * 100 = 50%
        $this->assertEqualsWithDelta(50.0, (float) $linea->porcentaje_cumplimiento, 0.01);

        $sinFactor = $detalle['lineas']->firstWhere('item', '001200');
        $this->assertTrue((bool) $sinFactor->sin_factor);
    }

    public function test_detalle_expone_la_categoria_por_linea(): void
    {
        $this->sembrar();
        $detalle = app(PedidosPorLiberarQuery::class)->detallePedido('179');

        $this->assertSame('ESENCIAS Y VINAGRE', $detalle['lineas']->firstWhere('item', '001131')->categoria);
        $this->assertNull($detalle['lineas']->firstWhere('item', '001200')->categoria);
    }

    public function test_detalle_inexistente_es_null(): void
    {
        $this->sembrar();
        $this->assertNull(app(PedidosPorLiberarQuery::class)->detallePedido('999'));
    }

    public function test_calcula_volumen_y_sugiere_contenedor(): void
    {
        Cliente::create(['nit_cliente' => 'B1', 'cliente' => 'X', 'destino' => 'ESPAÑA']);
        // 24 cajas/embalaje, 25.137 m3/1e6 por caja -> cantidad 2400 => 100 cajas => 2,5137 m3
        ParametroItem::create(['item' => '001131', 'factor_kg' => 0.5, 'embalaje' => 24, 'volumen' => 25137]);
        LineaPedido::create([
            'pedido' => '200', 'item' => '001131', 'bodega' => '004', 'unidad_medida' => 'UN',
            'cantidad_pedida' => 2400, 'nit_cliente' => 'B1', 'nombre_cliente' => 'X',
            'estado' => 'PL', 'activo' => true, 'fecha_elaboracion' => '2026-08-04',
        ]);

        $resumen = app(PedidosPorLiberarQuery::class)->resumenPorPedido()->getCollection()->firstWhere('pedido', '200');
        $this->assertEqualsWithDelta(100.0, $resumen->cajas_total, 0.1);
        $this->assertFalse((bool) $resumen->cajas_incompleto);
        $this->assertEqualsWithDelta(2.51, $resumen->volumen_total, 0.01);
        $this->assertFalse((bool) $resumen->volumen_incompleto);
        $this->assertSame("Contenedor 20'", $resumen->contenedor_sugerido);

        $detalle = app(PedidosPorLiberarQuery::class)->detallePedido('200');
        $this->assertEqualsWithDelta(2.51, $detalle['totales']->volumen_total, 0.01);
        $this->assertSame("Contenedor 20'", $detalle['totales']->contenedor_sugerido);

        $linea = $detalle['lineas']->firstWhere('item', '001131');
        $this->assertEqualsWithDelta(100.0, (float) $linea->cajas_linea, 0.001);
    }

    public function test_cajas_linea_es_null_sin_embalaje(): void
    {
        $this->sembrar();
        // item 001131 en sembrar() no tiene embalaje definido
        $detalle = app(PedidosPorLiberarQuery::class)->detallePedido('179');
        $this->assertNull($detalle['lineas']->firstWhere('item', '001131')->cajas_linea);
    }

    public function test_sugiere_contenedor_40_y_alerta_de_cubicaje_excedido(): void
    {
        Cliente::create(['nit_cliente' => 'B2', 'cliente' => 'X', 'destino' => 'ESPAÑA']);
        // 1 caja/embalaje, volumen 40.000.000 -> 1 caja => 40 m3 (> 32, <= 60 -> 40')
        ParametroItem::create(['item' => '000001', 'factor_kg' => 0.5, 'embalaje' => 1, 'volumen' => 40000000]);
        // 1 caja/embalaje, volumen 70.000.000 -> 70 m3 (> 60 -> excede cubicaje)
        ParametroItem::create(['item' => '000002', 'factor_kg' => 0.5, 'embalaje' => 1, 'volumen' => 70000000]);

        LineaPedido::create([
            'pedido' => '201', 'item' => '000001', 'bodega' => '004', 'unidad_medida' => 'UN',
            'cantidad_pedida' => 1, 'nit_cliente' => 'B2', 'nombre_cliente' => 'X',
            'estado' => 'PL', 'activo' => true, 'fecha_elaboracion' => '2026-08-04',
        ]);
        LineaPedido::create([
            'pedido' => '202', 'item' => '000002', 'bodega' => '004', 'unidad_medida' => 'UN',
            'cantidad_pedida' => 1, 'nit_cliente' => 'B2', 'nombre_cliente' => 'X',
            'estado' => 'PL', 'activo' => true, 'fecha_elaboracion' => '2026-08-04',
        ]);

        $col = app(PedidosPorLiberarQuery::class)->resumenPorPedido()->getCollection();
        $this->assertSame("Contenedor 40'", $col->firstWhere('pedido', '201')->contenedor_sugerido);
        $this->assertSame('Excede cubicaje', $col->firstWhere('pedido', '202')->contenedor_sugerido);
    }

    public function test_sin_embalaje_o_volumen_marca_incompleto(): void
    {
        $this->sembrar();
        // item 001131 no tiene embalaje/volumen en sembrar()
        $p179 = app(PedidosPorLiberarQuery::class)->resumenPorPedido()->getCollection()->firstWhere('pedido', '179');

        $this->assertTrue((bool) $p179->volumen_incompleto);

        $detalle = app(PedidosPorLiberarQuery::class)->detallePedido('179');
        $linea = $detalle['lineas']->firstWhere('item', '001131');
        $this->assertTrue((bool) $linea->sin_volumen);
        $this->assertTrue($detalle['totales']->volumen_incompleto);
    }
}
