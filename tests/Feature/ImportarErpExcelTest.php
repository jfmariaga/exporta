<?php

namespace Tests\Feature;

use App\Models\Seguimiento;
use App\Services\ImportadorErp;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use RuntimeException;
use Tests\ConstruyeExcelErp;
use Tests\CreaUsuarios;
use Tests\TestCase;

class ImportarErpExcelTest extends TestCase
{
    use ConstruyeExcelErp, CreaUsuarios, RefreshDatabase;

    public function test_carga_la_hoja_de_lineas(): void
    {
        app(ImportadorErp::class)->importar($this->excelErp());

        $this->assertSame(5, DB::table('lineas_pedido')->count());
        $this->assertDatabaseHas('lineas_pedido', ['pedido' => '179', 'item' => '001131']);
        // clientes/parametros_item son maestros manuales (UI admin/clientes,
        // admin/parametros-item): la importación ya no los toca.
        $this->assertSame(0, DB::table('clientes')->count());
        $this->assertSame(0, DB::table('parametros_item')->count());
    }

    public function test_es_idempotente(): void
    {
        $ruta = $this->excelErp();
        app(ImportadorErp::class)->importar($ruta);
        app(ImportadorErp::class)->importar($ruta);

        $this->assertSame(5, DB::table('lineas_pedido')->count());
    }

    public function test_conserva_el_seguimiento_al_reimportar(): void
    {
        $ruta = $this->excelErp();
        app(ImportadorErp::class)->importar($ruta);

        Seguimiento::create([
            'pedido' => '179', 'item' => '001131', 'estado_materiales' => 'PEND. REQUISICION', 'inventario' => 100,
        ]);

        app(ImportadorErp::class)->importar($ruta);

        $this->assertDatabaseHas('seguimientos', ['pedido' => '179', 'item' => '001131', 'estado_materiales' => 'PEND. REQUISICION']);
        $this->assertSame(1, DB::table('seguimientos')->count());
    }

    public function test_marca_inactivas_las_lineas_ausentes(): void
    {
        app(ImportadorErp::class)->importar($this->excelErp());
        $this->assertSame(5, DB::table('lineas_pedido')->where('activo', true)->count());

        $reducido = $this->excelErp(['lineas' => [
            ['PEDIDO', 'BODEGA', 'ITEM', 'DESCRIPCION', 'GRUPOITEM', 'FECHAELABORACION', 'UNIDADMEDIDA', 'CANTIDADPEDIDA', 'CANTIDADDESPACHADA', 'FECHAREQUERIDA', 'FECHADESPACHO', 'PRECIOTOTALNETO', 'NITCLIENTE', 'NOMBRECLIENTE', 'ESTADO', 'FECHAULTIMOMOVIMIENTO', 'USUARIO'],
            ['179', '004', '001131', 'ESENC COCO', 'ESENCIAS', '2026-08-04', 'UN', 2400, 0, '2026-08-05', '2026-08-04', 5568, 'B66061078', 'DISTRIBAR', 'PL', '2026-08-04', 'SBERNAL'],
        ]]);
        app(ImportadorErp::class)->importar($reducido);

        $this->assertSame(1, DB::table('lineas_pedido')->where('activo', true)->count());
        $this->assertSame(5, DB::table('lineas_pedido')->count());
    }

    public function test_hoja_invalida_lanza_error_y_no_deja_datos(): void
    {
        $malo = $this->excelErp(['lineas' => [['ColumnaRara'], ['x']]]);

        try {
            app(ImportadorErp::class)->importar($malo);
            $this->fail('Se esperaba una excepción.');
        } catch (RuntimeException) {
            // esperado
        }

        $this->assertSame(0, DB::table('lineas_pedido')->count());
    }

    private function archivoFake(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('erp.xlsx', file_get_contents($this->excelErp()));
    }

    public function test_componente_de_importacion_requiere_permiso(): void
    {
        Volt::actingAs($this->usuarioCon([Permisos::PEDIDOS_VER]))
            ->test('pedidos.index')
            ->set('archivo', $this->archivoFake())
            ->call('importar')
            ->assertStatus(403);
    }

    public function test_lista_de_pedidos_se_renderiza(): void
    {
        Volt::actingAs($this->usuarioCon([Permisos::PEDIDOS_VER]))
            ->test('pedidos.index')
            ->assertOk();
    }
}
