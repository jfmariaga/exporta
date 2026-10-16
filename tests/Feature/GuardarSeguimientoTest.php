<?php

namespace Tests\Feature;

use App\Models\InventarioSincbox;
use App\Models\LineaPedido;
use App\Models\ParametroItem;
use App\Models\Seguimiento;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\CreaUsuarios;
use Tests\TestCase;

class GuardarSeguimientoTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    private function lineas(): void
    {
        foreach (['001131' => 2400, '002389' => 1250] as $item => $cant) {
            LineaPedido::create([
                'pedido' => '179', 'item' => $item, 'bodega' => '004', 'unidad_medida' => 'UN',
                'cantidad_pedida' => $cant, 'nit_cliente' => 'B66061078', 'nombre_cliente' => 'DISTRIBAR',
                'estado' => 'PL', 'activo' => true, 'descripcion' => "DESC {$item}",
            ]);
        }
    }

    public function test_guarda_seguimiento_a_nivel_de_pedido(): void
    {
        $this->lineas();
        $user = $this->usuarioCon([Permisos::PEDIDOS_VER, Permisos::SEGUIMIENTO_EDITAR]);

        Volt::actingAs($user)
            ->test('pedidos.show', ['pedido' => '179'])
            ->set('estadoSeguimiento', 'Listo para cargar')
            ->set('fechaTentativa', '2026-08-26')
            ->call('guardarPedido')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('seguimiento_pedidos', [
            'pedido' => '179', 'estado_seguimiento' => 'Listo para cargar',
            'actualizado_por' => $user->id,
        ]);
    }

    public function test_guarda_seguimiento_de_lineas_con_inventario(): void
    {
        $this->lineas();
        InventarioSincbox::create(['item' => '001131', 'cantidad_total' => 4800]);
        $user = $this->usuarioCon([Permisos::PEDIDOS_VER, Permisos::SEGUIMIENTO_EDITAR]);

        Volt::actingAs($user)
            ->test('pedidos.show', ['pedido' => '179'])
            ->set('lineasSeg.001131.estado_materiales', 'PEND. ORDEN DE COMPRA')
            ->set('lineasSeg.001131.observaciones', 'Por programar')
            ->set('lineasSeg.001131.fecha_programa', '2026-09-01')
            ->set('lineasSeg.001131.inventario', '4800')
            ->call('guardarLineas')
            ->assertHasNoErrors();

        $s = Seguimiento::where(['pedido' => '179', 'item' => '001131'])->firstOrFail();
        $this->assertSame('PEND. ORDEN DE COMPRA', $s->estado_materiales);
        $this->assertSame('Por programar', $s->observaciones);
        $this->assertSame('2026-09-01', $s->fecha_programa->format('Y-m-d'));
        $this->assertSame('4800.000', (string) $s->inventario);
        $this->assertSame($user->id, $s->actualizado_por);
        $this->assertSame('2400.000', (string) $s->cantidad);

        // Segundo guardado actualiza, no duplica
        Volt::actingAs($user)
            ->test('pedidos.show', ['pedido' => '179'])
            ->set('lineasSeg.001131.estado_materiales', 'OC ENVIADA A PROVEEDOR')
            ->call('guardarLineas');

        $this->assertSame(1, DB::table('seguimientos')->where('pedido', '179')->count());
        $this->assertDatabaseHas('seguimientos', ['pedido' => '179', 'item' => '001131', 'estado_materiales' => 'OC ENVIADA A PROVEEDOR']);
    }

    public function test_estado_materiales_rechaza_valor_fuera_de_la_lista(): void
    {
        $this->lineas();
        $user = $this->usuarioCon([Permisos::PEDIDOS_VER, Permisos::SEGUIMIENTO_EDITAR]);

        Volt::actingAs($user)
            ->test('pedidos.show', ['pedido' => '179'])
            ->set('lineasSeg.001131.estado_materiales', 'CUALQUIER COSA')
            ->call('guardarLineas')
            ->assertHasErrors('lineasSeg.001131.estado_materiales');
    }

    public function test_sin_permiso_no_puede_guardar(): void
    {
        $this->lineas();
        $user = $this->usuarioCon([Permisos::PEDIDOS_VER]);

        Volt::actingAs($user)
            ->test('pedidos.show', ['pedido' => '179'])
            ->set('estadoSeguimiento', 'En validación')
            ->call('guardarPedido')
            ->assertStatus(403);
    }

    public function test_filtro_por_categoria_en_el_detalle(): void
    {
        $this->lineas();
        ParametroItem::create(['item' => '001131', 'descripcion' => 'ESENC', 'factor_kg' => 0.5, 'categoria' => 'ESENCIAS Y VINAGRE']);
        ParametroItem::create(['item' => '002389', 'descripcion' => 'CERNIDO', 'factor_kg' => 0.5, 'categoria' => 'INSTITUCIONAL']);

        $user = $this->usuarioCon([Permisos::PEDIDOS_VER]);

        $comp = Volt::actingAs($user)->test('pedidos.show', ['pedido' => '179']);

        // La UI de línea: select de estado de materiales + columnas Observaciones y Fecha programada
        $comp->assertSee('PEND. ORDEN DE COMPRA')
            ->assertSee('OC ENVIADA A PROVEEDOR')
            ->assertSee('Fecha programada')
            ->assertSee('Observaciones');

        $comp->assertViewHas('lineas', fn ($p) => $p->total() === 2);

        $comp->set('categoria', 'ESENCIAS Y VINAGRE')
            ->assertViewHas('lineas', fn ($p) => $p->total() === 1 && $p->first()->item === '001131');
    }
}
