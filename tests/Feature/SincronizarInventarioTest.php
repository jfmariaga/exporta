<?php

namespace Tests\Feature;

use App\Models\InventarioSincbox;
use App\Models\InventarioSincboxDetalle;
use App\Models\Seguimiento;
use App\Services\Inventario\ConsultaExistenciasSyncBox;
use App\Services\Inventario\InventarioSync;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use RuntimeException;
use Tests\CreaUsuarios;
use Tests\TestCase;

class SincronizarInventarioTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    /** @return array<int, object> */
    private function fila(string $code, float $amount, bool $bloqueado = false): array
    {
        return [(object) [
            'warehouse' => '004', 'ubication' => '02E0202A', 'code' => $code,
            'description' => "DESC {$code}", 'lote' => '50070000-001', 'product_id' => 1,
            'amount' => $amount, 'is_blocked' => $bloqueado, 'created' => '2026-10-01', 'comments' => 'Export.',
        ]];
    }

    private function fuenteCon(array $filas): ConsultaExistenciasSyncBox
    {
        return new class($filas) extends ConsultaExistenciasSyncBox
        {
            public function __construct(private array $filasFake) {}

            protected function filas(): array
            {
                return $this->filasFake;
            }
        };
    }

    public function test_sync_inserta_y_luego_actualiza_y_pone_en_cero_ausentes(): void
    {
        $sync1 = new InventarioSync($this->fuenteCon([
            ...$this->fila('001131', 500),
            ...$this->fila('002200', 300),
        ]));
        $r1 = $sync1->sincronizar();
        $this->assertSame(2, $r1['insertadas']);

        $sync2 = new InventarioSync($this->fuenteCon($this->fila('001131', 700)));
        $r2 = $sync2->sincronizar();
        $this->assertSame(0, $r2['insertadas']);
        $this->assertSame(1, $r2['actualizadas']);
        $this->assertSame(1, $r2['puestas_en_cero']);

        $this->assertEquals(700, InventarioSincbox::where('item', '001131')->value('cantidad_total'));
        $this->assertEquals(0, InventarioSincbox::where('item', '002200')->value('cantidad_total'));
    }

    public function test_sync_reemplaza_por_completo_el_detalle_fila_a_fila(): void
    {
        $sync1 = new InventarioSync($this->fuenteCon([
            ...$this->fila('001131', 500),
            ...$this->fila('002200', 300),
        ]));
        $sync1->sincronizar();
        $this->assertSame(2, InventarioSincboxDetalle::count());

        // Segunda corrida: sólo el 001131 -> el detalle del 002200 desaparece del todo.
        $sync2 = new InventarioSync($this->fuenteCon($this->fila('001131', 700)));
        $r2 = $sync2->sincronizar();

        $this->assertSame(1, $r2['filas_detalle']);
        $this->assertSame(1, InventarioSincboxDetalle::count());
        $this->assertSame(0, InventarioSincboxDetalle::where('item', '002200')->count());

        $fila = InventarioSincboxDetalle::where('item', '001131')->first();
        $this->assertEquals(700, $fila->cantidad);
        $this->assertSame('004', $fila->warehouse);
        $this->assertSame('02E0202A', $fila->ubication);
        $this->assertSame('50070000-001', $fila->lote);
        $this->assertSame('2026-10-01', $fila->creado->format('Y-m-d'));
    }

    public function test_sync_con_cero_filas_aborta_sin_vaciar(): void
    {
        $sync = new InventarioSync($this->fuenteCon($this->fila('001131', 500)));
        $sync->sincronizar();

        $vacio = new InventarioSync($this->fuenteCon([]));
        $this->expectException(RuntimeException::class);
        try {
            $vacio->sincronizar();
        } finally {
            $this->assertEquals(500, InventarioSincbox::where('item', '001131')->value('cantidad_total'));
        }
    }

    public function test_sync_no_toca_el_seguimiento(): void
    {
        Seguimiento::create(['pedido' => '179', 'item' => '001131', 'inventario' => 50]);

        $sync = new InventarioSync($this->fuenteCon($this->fila('001131', 999)));
        $sync->sincronizar();

        $this->assertDatabaseHas('seguimientos', ['pedido' => '179', 'item' => '001131', 'inventario' => 50]);
        $this->assertSame(1, \DB::table('seguimientos')->count());
    }

    public function test_modo_api_consulta_el_proyecto_puente_por_http(): void
    {
        config([
            'inventario.modo' => 'api',
            'inventario.api_url' => 'http://api-datos-panal.test',
            'inventario.api_token' => 'un-token',
        ]);

        Http::fake([
            'api-datos-panal.test/api/inventario/existencias' => Http::response(['data' => [
                ['warehouse' => '004', 'ubication' => '02E0202A', 'code' => '001131',
                    'description' => 'ESENC COCO', 'lote' => 'L1', 'product_id' => 1,
                    'amount' => 500, 'is_blocked' => false, 'created' => '2026-10-01', 'comments' => 'Export.'],
            ]]),
        ]);

        $r = app(InventarioSync::class)->sincronizar();

        $this->assertSame(1, $r['insertadas']);
        $this->assertEquals(500, InventarioSincbox::where('item', '001131')->value('cantidad_total'));

        Http::assertSent(fn ($request) => $request->url() === 'http://api-datos-panal.test/api/inventario/existencias'
            && $request->hasHeader('Authorization', 'Bearer un-token'));
    }

    public function test_consulta_syncbox_suma_amount_por_code_normalizado(): void
    {
        $fuente = new class extends ConsultaExistenciasSyncBox
        {
            protected function filas(): array
            {
                return [
                    (object) [
                        'warehouse' => '004', 'ubication' => 'A1', 'code' => 1131,
                        'description' => 'ESENC COCO', 'lote' => 'L1', 'product_id' => 1,
                        'amount' => 300, 'is_blocked' => false, 'created' => '2026-10-01', 'comments' => 'Export.',
                    ],
                    (object) [
                        'warehouse' => '004', 'ubication' => 'A2', 'code' => 1131,
                        'description' => 'ESENC COCO', 'lote' => 'L2', 'product_id' => 1,
                        'amount' => 200, 'is_blocked' => true, 'created' => '2026-10-02', 'comments' => 'Export.',
                    ],
                ];
            }
        };

        $out = $fuente->existenciasPorItem();

        $this->assertArrayHasKey('001131', $out);
        $this->assertEquals(500, $out['001131']['cantidad_total']);
        $this->assertCount(2, $out['001131']['detalle']);
        $this->assertTrue($out['001131']['detalle'][1]['is_blocked']);

        $filas = $fuente->filasNormalizadas();
        $this->assertCount(2, $filas);
        $this->assertSame('001131', $filas[0]['item']);
        $this->assertSame('L2', $filas[1]['lote']);
    }

    public function test_boton_sincronizar_renderiza_el_resumen_sin_error(): void
    {
        $this->instance(ConsultaExistenciasSyncBox::class, $this->fuenteCon($this->fila('001131', 500)));

        Volt::actingAs($this->usuarioCon([Permisos::SEGUIMIENTO_EDITAR, Permisos::ERP_IMPORTAR]))
            ->test('inventario.index')
            ->call('sincronizar')
            ->assertOk()
            ->assertSee('Sincronización con SyncBox completada.');
    }
}
