<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\LineaPedido;
use App\Models\SeguimientoPedido;
use App\Services\CorreoCarguesBuilder;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreaUsuarios;
use Tests\TestCase;

class CorreoCarguesTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    private function sembrar(): void
    {
        Cliente::create(['nit_cliente' => 'B66061078', 'cliente' => 'DISTRIBAR', 'destino' => 'ESPAÑA']);

        foreach (['179', '180'] as $pedido) {
            LineaPedido::create([
                'pedido' => $pedido, 'item' => '001131', 'bodega' => '004', 'unidad_medida' => 'UN',
                'cantidad_pedida' => 2400, 'nit_cliente' => 'B66061078', 'nombre_cliente' => 'DISTRIBAR',
                'estado' => 'PL', 'activo' => true, 'descripcion' => 'ESENC COCO',
            ]);
        }

        // 179 con fecha tentativa -> entra ; 180 sin fecha -> no entra
        SeguimientoPedido::create(['pedido' => '179', 'estado_seguimiento' => 'Listo para cargar', 'fecha_tentativa_cargue' => '2026-08-26']);
        SeguimientoPedido::create(['pedido' => '180', 'estado_seguimiento' => 'Pendiente']);
    }

    public function test_incluye_solo_pedidos_con_fecha_tentativa(): void
    {
        $this->sembrar();
        $texto = (new CorreoCarguesBuilder)->texto();

        $this->assertStringContainsString('Pedido 179', $texto);
        $this->assertStringNotContainsString('Pedido 180', $texto);
        $this->assertStringContainsString('Pedido disponible para cargue sobre el 26/08/2026', $texto);
    }

    public function test_pantalla_requiere_permiso(): void
    {
        $this->sembrar();

        $this->actingAs($this->usuarioCon([]))->get('/cargues/correo')->assertForbidden();
        $this->actingAs($this->usuarioCon([Permisos::CORREO_GENERAR]))->get('/cargues/correo')->assertOk();
    }
}
