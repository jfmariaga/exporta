<?php

namespace App\Support;

/**
 * Catálogo fijo de permisos de la aplicación.
 *
 * El catálogo se siembra con RolesPermisosSeeder y la UI lo muestra en sólo lectura:
 * no se crean ni borran permisos desde la aplicación (constitución, Principio V).
 */
final class Permisos
{
    public const PEDIDOS_VER = 'pedidos.ver';

    public const SEGUIMIENTO_EDITAR = 'seguimiento.editar';

    public const CORREO_GENERAR = 'correo.generar';

    public const ERP_IMPORTAR = 'erp.importar';

    public const ADMIN_USUARIOS = 'admin.usuarios';

    public const ADMIN_ROLES = 'admin.roles';

    /** @var list<string> */
    public const TODOS = [
        self::PEDIDOS_VER,
        self::SEGUIMIENTO_EDITAR,
        self::CORREO_GENERAR,
        self::ERP_IMPORTAR,
        self::ADMIN_USUARIOS,
        self::ADMIN_ROLES,
    ];

    /** Etiquetas legibles para la UI. @return array<string,string> */
    public static function etiquetas(): array
    {
        return [
            self::PEDIDOS_VER => 'Ver pedidos por liberar',
            self::SEGUIMIENTO_EDITAR => 'Editar seguimiento (materiales, observaciones, fecha)',
            self::CORREO_GENERAR => 'Generar correo de cargues',
            self::ERP_IMPORTAR => 'Importar datos del ERP',
            self::ADMIN_USUARIOS => 'Administrar usuarios',
            self::ADMIN_ROLES => 'Administrar roles y permisos',
        ];
    }
}
