<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de integridad para no dejar el sistema sin un Administrador activo
 * y para proteger el rol Administrador. Son reglas de integridad, no de
 * autorización de negocio (constitución, Principio V).
 */
final class AdministradorGuard
{
    public static function esRolProtegido(Role|string $rol): bool
    {
        $nombre = $rol instanceof Role ? $rol->name : $rol;

        return $nombre === RolesBase::ADMINISTRADOR;
    }

    /** Número de usuarios activos con rol Administrador. */
    public static function administradoresActivos(): int
    {
        return User::query()
            ->activos()
            ->role(RolesBase::ADMINISTRADOR)
            ->count();
    }

    /**
     * Lanza ValidationException si la acción sobre $usuario dejaría al sistema
     * sin ningún Administrador activo.
     */
    public static function garantizarNoEsUltimo(User $usuario, string $campo = 'usuario'): void
    {
        if (! $usuario->hasRole(RolesBase::ADMINISTRADOR) || ! $usuario->activo) {
            return;
        }

        if (self::administradoresActivos() <= 1) {
            throw ValidationException::withMessages([
                $campo => 'Debe existir al menos un usuario Administrador activo.',
            ]);
        }
    }
}
