<?php

namespace Tests\Feature;

use App\Models\Seguimiento;
use App\Services\Erp\ConsultaDirectaErp;
use App\Services\Erp\LineasPedidoSync;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use RuntimeException;
use Tests\CreaUsuarios;
use Tests\TestCase;

class SincronizarErpTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    /** @return array<string,mixed> */
    private function fila(string $pedido, string $item, string $estado = 'PL', float $cant = 100): array
    {
        return [
            'pedido' => $pedido, 'item' => $item, 'bodega' => '004',
            'descripcion' => "DESC {$item}", 'grupo_item' => 'VARIOS',
            'fecha_elaboracion' => '2026-08-04', 'unidad_medida' => 'UN',
            'cantidad_pedida' => $cant, 'cantidad_despachada' => 0,
            'fecha_requerida' => '2026-08-05', 'fecha_despacho' => null,
            'precio_total_neto' => 1000, 'nit_cliente' => 'B66061078',
            'nombre_cliente' => 'DISTRIBAR', 'estado' => $estado,
            'fecha_ultimo_movimiento' => '2026-08-04', 'usuario' => 'SBERNAL',
        ];
    }

    public function test_sync_inserta_y_luego_actualiza_y_desactiva_ausentes(): void
    {
        $sync = app(LineasPedidoSync::class);

        $r1 = $sync->sincronizar([$this->fila('179', '001131'), $this->fila('180', '030848', 'PP')]);
        $this->assertSame(2, $r1['insertadas']);
        $this->assertSame(2, DB::table('lineas_pedido')->where('activo', true)->count());

        // Segunda corrida: sólo el 179 -> el 180 queda inactivo, sin borrarse
        $r2 = $sync->sincronizar([$this->fila('179', '001131', 'PL', 2400)]);
        $this->assertSame(0, $r2['insertadas']);
        $this->assertSame(1, $r2['actualizadas']);
        $this->assertSame(1, $r2['desactivadas']);
        $this->assertSame(1, DB::table('lineas_pedido')->where('activo', true)->count());
        $this->assertSame(2, DB::table('lineas_pedido')->count());
        $this->assertEquals(2400, DB::table('lineas_pedido')->where(['pedido' => '179', 'item' => '001131'])->value('cantidad_pedida'));
    }

    public function test_sync_con_cero_filas_aborta_sin_vaciar(): void
    {
        $sync = app(LineasPedidoSync::class);
        $sync->sincronizar([$this->fila('179', '001131')]);

        $this->expectException(RuntimeException::class);
        try {
            $sync->sincronizar([]);
        } finally {
            $this->assertSame(1, DB::table('lineas_pedido')->where('activo', true)->count());
        }
    }

    public function test_sync_no_toca_el_seguimiento(): void
    {
        $sync = app(LineasPedidoSync::class);
        $sync->sincronizar([$this->fila('179', '001131')]);
        Seguimiento::create(['pedido' => '179', 'item' => '001131', 'inventario' => 50]);

        $sync->sincronizar([$this->fila('179', '001131', 'PL', 999)]);

        $this->assertDatabaseHas('seguimientos', ['pedido' => '179', 'item' => '001131', 'inventario' => 50]);
        $this->assertSame(1, DB::table('seguimientos')->count());
    }

    public function test_modo_api_consulta_el_proyecto_puente_por_http(): void
    {
        config([
            'erp.modo' => 'api',
            'erp.api_url' => 'http://api-datos-panal.test',
            'erp.api_token' => 'un-token',
        ]);

        Http::fake([
            'api-datos-panal.test/api/erp/pedidos-pl' => Http::response(['data' => [
                ['pedido' => '179', 'bodega' => '004', 'item' => 1131,
                    'descripcion' => 'ESENC COCO', 'grupoitem' => 'ESENCIAS - VIDRIO',
                    'fechaelaboracion' => '2026-08-04', 'unidadmedida' => 'UN',
                    'cantidadpedida' => 2400, 'cantidaddespachada' => 0,
                    'fecharequerida' => '2026-08-05', 'fechadespacho' => null,
                    'preciototalneto' => 5568, 'nitcliente' => 'B66061078',
                    'nombrecliente' => 'DISTRIBAR', 'estado' => 'PL',
                    'fechaultimomovimiento' => '2026-08-04', 'usuario' => 'SBERNAL'],
            ]]),
        ]);

        $r = app(ConsultaDirectaErp::class)->sincronizar();

        $this->assertSame(1, $r['insertadas']);
        $this->assertDatabaseHas('lineas_pedido', ['pedido' => '179', 'item' => '001131']);

        Http::assertSent(fn ($request) => $request->url() === 'http://api-datos-panal.test/api/erp/pedidos-pl'
            && $request->hasHeader('Authorization', 'Bearer un-token'));
    }

    public function test_consulta_directa_mapea_alias_del_erp_y_normaliza_item(): void
    {
        // Filas crudas tal como llegarían de SQL Server (alias en minúscula, item numérico).
        $fuente = new class(app(LineasPedidoSync::class)) extends ConsultaDirectaErp
        {
            protected function filas(): array
            {
                return [
                    (object) [
                        'pedido' => '179', 'bodega' => '004', 'item' => 1131,
                        'descripcion' => 'ESENC COCO', 'grupoitem' => 'ESENCIAS - VIDRIO',
                        'fechaelaboracion' => '2026-08-04', 'unidadmedida' => 'UN',
                        'cantidadpedida' => 2400, 'cantidaddespachada' => 0,
                        'fecharequerida' => '2026-08-05', 'fechadespacho' => null,
                        'preciototalneto' => 5568, 'nitcliente' => 'B66061078',
                        'nombrecliente' => 'DISTRIBAR', 'estado' => 'PL',
                        'fechaultimomovimiento' => '2026-08-04', 'usuario' => 'SBERNAL',
                    ],
                ];
            }
        };

        $r = $fuente->sincronizar();

        $this->assertSame(1, $r['insertadas']);
        $this->assertDatabaseHas('lineas_pedido', [
            'pedido' => '179', 'item' => '001131', 'grupo_item' => 'ESENCIAS - VIDRIO',
            'estado' => 'PL', 'nit_cliente' => 'B66061078', 'activo' => true,
        ]);
        $this->assertEquals(2400, DB::table('lineas_pedido')->where('item', '001131')->value('cantidad_pedida'));
    }

    public function test_boton_sincronizar_renderiza_el_resumen_sin_error(): void
    {
        $this->instance(ConsultaDirectaErp::class, new class(app(LineasPedidoSync::class)) extends ConsultaDirectaErp
        {
            protected function filas(): array
            {
                return [
                    (object) [
                        'pedido' => '179', 'bodega' => '004', 'item' => 1131,
                        'descripcion' => 'ESENC COCO', 'grupoitem' => 'ESENCIAS - VIDRIO',
                        'fechaelaboracion' => '2026-08-04', 'unidadmedida' => 'UN',
                        'cantidadpedida' => 2400, 'cantidaddespachada' => 0,
                        'fecharequerida' => '2026-08-05', 'fechadespacho' => null,
                        'preciototalneto' => 5568, 'nitcliente' => 'B66061078',
                        'nombrecliente' => 'DISTRIBAR', 'estado' => 'PL',
                        'fechaultimomovimiento' => '2026-08-04', 'usuario' => 'SBERNAL',
                    ],
                ];
            }
        });

        Volt::actingAs($this->usuarioCon([Permisos::PEDIDOS_VER, Permisos::ERP_IMPORTAR]))
            ->test('pedidos.index')
            ->call('sincronizar')
            ->assertOk()
            ->assertSee('+1')
            ->assertSee('0 baja');
    }
}
