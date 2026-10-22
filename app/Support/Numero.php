<?php

namespace App\Support;

/**
 * Formato numérico español (coma decimal, punto de miles) para la UI,
 * recortando ceros decimales sobrantes (p. ej. 25137,0000 -> 25.137).
 */
final class Numero
{
    public static function decimal(?float $valor, int $maxDecimales = 4): string
    {
        if ($valor === null) {
            return '—';
        }

        $formateado = number_format($valor, $maxDecimales, ',', '.');

        if (str_contains($formateado, ',')) {
            $formateado = rtrim(rtrim($formateado, '0'), ',');
        }

        return $formateado;
    }
}
