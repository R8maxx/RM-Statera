<?php

declare(strict_types=1);

namespace App\Domain\Plataforma;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lo que el plan de una organización le deja añadir (punto 43).
 *
 * **Las cuentas que cuentan son las de quien trabaja en el SGSI**: no
 * desactivadas, aceptadas o con la invitación pendiente —una invitación ocupa
 * su sitio—, y nunca el auditor externo. Al auditor se le da acceso para que
 * audite; cobrarle el asiento al cliente sería castigarle por dejarse auditar.
 * Quien administra la plataforma no cuenta nunca, ni siquiera cuando es además
 * usuario de la organización (punto 45): a los administradores del programa no
 * se les cobra.
 *
 * Sin plan, o con el límite a nulo, no hay límite.
 */
final class LimitesDelPlan
{
    public function cuentasOcupadas(Organizacion $organizacion): int
    {
        return User::query()
            ->where('users.organizacion_id', $organizacion->id)
            ->whereNull('users.desactivada_en')
            ->where('users.es_plataforma', false)
            ->whereNotExists(function ($consulta): void {
                $consulta->select(DB::raw(1))
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->whereColumn('model_has_roles.organizacion_id', 'users.organizacion_id')
                    ->where('model_has_roles.model_type', (new User)->getMorphClass())
                    ->where('roles.name', Rol::Auditor->value);
            })
            ->count();
    }

    public function puedeAnadirCuenta(Organizacion $organizacion, Rol $rol): bool
    {
        $limite = $organizacion->plan?->limite_cuentas;

        return $rol === Rol::Auditor || $limite === null || $this->cuentasOcupadas($organizacion) < $limite;
    }

    /**
     * Cuenta por el scope de organización: se llama desde una petición del
     * propio cliente, con su contexto puesto.
     */
    public function puedeAnadirSistema(Organizacion $organizacion): bool
    {
        $limite = $organizacion->plan?->limite_sistemas;

        return $limite === null || Sistema::query()->count() < $limite;
    }
}
