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
}
