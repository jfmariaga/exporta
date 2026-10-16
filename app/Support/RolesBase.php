<?php

namespace App\Support;

/**
 * Roles sembrados por defecto. Sólo el seeder y las reglas de integridad
 * (protección del rol Administrador) referencian estos nombres.
 */
final class RolesBase
{
    public const ADMINISTRADOR = 'Administrador';

    public const PLANEADOR = 'Planeador';

    public const CONSULTA = 'Consulta';

    /** Permisos por rol base. @return array<string,list<string>> */
    public static function permisos(): array
    {
        return [
            self::ADMINISTRADOR => Permisos::TODOS,
            self::PLANEADOR => [
                Permisos::PEDIDOS_VER,
                Permisos::SEGUIMIENTO_EDITAR,
                Permisos::CORREO_GENERAR,
            ],
            self::CONSULTA => [
                Permisos::PEDIDOS_VER,
            ],
        ];
    }
}
