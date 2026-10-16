<?php

namespace App\Support;

/**
 * Estados del ERP que se consideran "pedidos por liberar".
 * Centraliza el filtro exigido por la especificación (FR-003 de la feature 001).
 */
final class EstadosPedido
{
    /** @var list<string> */
    public const POR_LIBERAR = ['PL', 'PP'];
}
