<?php

namespace Tests\Unit;

use App\Models\Seguimiento;
use App\Models\SeguimientoPedido;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeguimientoDerivadosTest extends TestCase
{
    #[DataProvider('estados')]
    public function test_alerta_por_estado_a_nivel_de_pedido(string $estado, string $esperado): void
    {
        $s = new SeguimientoPedido(['estado_seguimiento' => $estado]);

        $this->assertSame($esperado, $s->alerta);
    }

    /** @return list<array{string,string}> */
    public static function estados(): array
    {
        return [
            ['Pendiente', '🔴'],
            ['En validación', '🟡'],
            ['Listo para cargar', '✅'],
        ];
    }

    public function test_comentario_correo_sin_fecha(): void
    {
        $s = new SeguimientoPedido(['estado_seguimiento' => 'Pendiente']);

        $this->assertSame('PENDIENTE POR DEFINIR FECHA', $s->comentario_correo);
    }

    public function test_comentario_correo_con_fecha(): void
    {
        $s = new SeguimientoPedido([
            'estado_seguimiento' => 'Listo para cargar',
            'fecha_tentativa_cargue' => '2026-08-26',
        ]);

        $this->assertSame('Pedido disponible para cargue sobre el 26/08/2026', $s->comentario_correo);
    }

    public function test_porcentaje_cumplimiento_a_nivel_de_linea(): void
    {
        // inventario / cantidad * 100 -> 3000 / 2400 * 100 = 125%
        $s = new Seguimiento(['cantidad' => 2400, 'inventario' => 3000]);
        $this->assertSame(125.0, $s->porcentaje_cumplimiento);
    }

    public function test_porcentaje_cumplimiento_null_sin_inventario(): void
    {
        $s = new Seguimiento(['cantidad' => 2400, 'inventario' => null]);
        $this->assertNull($s->porcentaje_cumplimiento);
    }
}
