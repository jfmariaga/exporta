<?php

namespace App\Enums;

/**
 * Estado de seguimiento de una línea de pedido (equivalente a la columna ESTADO
 * de la hoja BD_Seguimiento del MVP). El valor almacenado es el texto legible
 * para casar con el MVP.
 */
enum EstadoSeguimiento: string
{
    case Pendiente = 'Pendiente';
    case EnValidacion = 'En validación';
    case ListoParaCargar = 'Listo para cargar';

    /** Emoji de alerta tipo semáforo. */
    public function alerta(): string
    {
        return match ($this) {
            self::Pendiente => '🔴',
            self::EnValidacion => '🟡',
            self::ListoParaCargar => '✅',
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $e) => $e->value, self::cases());
    }
}
