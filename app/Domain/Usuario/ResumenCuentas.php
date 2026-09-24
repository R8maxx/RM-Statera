<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Autorizacion\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as Consulta;

/**
 * Las cifras de la lista de cuentas (§ 4.19).
 *
 * Aquí y no en el controlador por la regla de siempre: son preguntas del
 * dominio, y la del auditor sin alcance es de las que un informe querrá hacer.
 * Cada consulta se acota a mano por `organizacion_id`, porque `users` está
 * fuera de las tres capas.
 */
final class ResumenCuentas
{
    public function invitadas(int $organizacionId): int
    {
        return User::query()->where('organizacion_id', $organizacionId)->invitadas()->count();
    }

    /** Cuentas que entran y todavía no pueden escribir: les falta el segundo factor. */
    public function sinDosFactores(int $organizacionId): int
    {
        return User::query()
            ->where('organizacion_id', $organizacionId)
            ->whereNull('desactivada_en')
            ->whereNotNull('activada_en')
            ->whereNull('two_factor_confirmed_at')
            ->count();
    }

    /**
     * Auditores que ven la organización entera porque no tienen sistemas.
     *
     * **Ninguna cuenta nueva puede quedar así**: `AlcanceDeCuenta` exige al
     * menos un sistema para ese rol. Pero una cuenta de auditor anterior al
     * § 4.19 —o una a la que se le borró su único sistema, porque la fila del
     * alcance se va en cascada con él— no tiene filas, y sin filas el alcance no
     * acota. Se cuenta para que se vea, en vez de cerrarle el paso sin avisar.
     */
    public function auditoresSinAlcance(int $organizacionId): int
    {
        return $this->auditores($organizacionId)
            ->whereNull('desactivada_en')
            ->whereDoesntHave('alcance')
            ->count();
    }

    /** @return Builder<User> */
    private function auditores(int $organizacionId): Builder
    {
        return User::query()
            ->where('organizacion_id', $organizacionId)
            ->whereExists(function (Consulta $rol) use ($organizacionId): void {
                $rol->selectRaw('1')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', (new User)->getMorphClass())
                    ->where('model_has_roles.organizacion_id', $organizacionId)
                    ->where('roles.name', Rol::Auditor->value);
            });
    }
}
