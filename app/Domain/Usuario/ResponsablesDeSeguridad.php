<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Autorizacion\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Query\Builder;

/**
 * Quién puede seguir firmando en una organización.
 *
 * El dominio de cuentas tiene una sola regla que no puede romperse: **ninguna
 * organización se queda sin un responsable de seguridad que pueda entrar**. Sin
 * él nadie da de alta cuentas, nadie acepta un riesgo y nadie aprueba un
 * documento, y la única salida sería tocar la base a mano.
 */
final class ResponsablesDeSeguridad
{
    /** Si, quitando a esta cuenta, queda otro responsable que entre. */
    public function quedaOtro(User $excepto): bool
    {
        return User::query()
            ->where('organizacion_id', $excepto->organizacion_id)
            ->whereKeyNot($excepto->id)
            ->whereNull('desactivada_en')
            ->whereNotNull('activada_en')
            ->where(fn ($consulta) => $consulta->whereNull('acceso_hasta')->orWhereDate('acceso_hasta', '>=', today()))
            ->whereExists(function (Builder $rol) use ($excepto): void {
                $rol->selectRaw('1')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', $excepto->getMorphClass())
                    ->where('model_has_roles.organizacion_id', $excepto->organizacion_id)
                    ->where('roles.name', Rol::ResponsableSeguridad->value);
            })
            ->exists();
    }
}
