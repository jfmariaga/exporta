<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Fija la fórmula de toneladas de forma aislada:
 * toneladas = cantidad_pedida * factor_kg / 1000.
 */
class ToneladasTest extends TestCase
{
    private function toneladas(float $cantidad, ?float $factor): ?float
    {
        return $factor === null ? null : $cantidad * $factor / 1000;
    }

    public function test_linea_con_factor(): void
    {
        $this->assertEqualsWithDelta(1.31790, $this->toneladas(2400, 0.549125), 0.00001);
    }

    public function test_suma_de_lineas(): void
    {
        $total = $this->toneladas(2400, 0.549125) + $this->toneladas(1000, 0.2225);
        $this->assertEqualsWithDelta(1.31790 + 0.22250, $total, 0.00001);
    }

    public function test_item_sin_factor_es_null(): void
    {
        $this->assertNull($this->toneladas(999, null));
    }
}
