<?php

namespace App\Enums;

/**
 * Estado de materiales de una línea de pedido (seguimiento a nivel de línea).
 * Lista cerrada de opciones que elige planeación.
 */
enum EstadoMateriales: string
{
    case PendMaterialEmpaque = 'PEND. MATERIAL DE EMPAQUE';
    case PendMaterialMateriaPrima = 'PEND. MATERIAL MATERIA PRIMA';
    case PendRequisicion = 'PEND. REQUISICION';
    case PendOrdenCompra = 'PEND. ORDEN DE COMPRA';
    case OcEnviadaProveedor = 'OC ENVIADA A PROVEEDOR';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(fn (self $e) => $e->value, self::cases());
    }
}
