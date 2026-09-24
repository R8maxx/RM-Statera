<?php

declare(strict_types=1);

namespace App\Domain\Autorizacion\Concerns;

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Database\Eloquent\Builder;

/**
 * El modelo se acota a los sistemas del alcance de la cuenta (§ 4.19).
 *
 * Lo llevan **todos los modelos cuya tabla tiene `sistema_id`**, más los que
 * cuelgan de un sistema sin la columna —un activo, por su N:M; un riesgo, por
 * sus activos; una evidencia, por sus implantaciones—. Que ninguno se quede
 * fuera lo comprueba `AlcanceDelAuditorTest`, que recorre el esquema.
 *
 * Sin alcance en el contexto no hace nada, que es el caso de todas las cuentas
 * salvo la del auditor externo. **Se suma a las tres capas y no quita
 * ninguna**: es un filtro dentro del tenant, no una frontera entre tenants.
 *
 * Por defecto acota por `sistema_id`. Un modelo con otro camino sobrescribe
 * `acotarAlAlcance()`; los que pasan por una relación usan `whereHas`, y ahí
 * no hace falta repetir la lista: la consulta de la relación ya lleva este
 * mismo scope en el modelo del otro lado.
 */
trait AcotadoPorAlcance
{
    public static function bootAcotadoPorAlcance(): void
    {
        static::addGlobalScope('alcance', static function (Builder $consulta): void {
            $sistemas = app(ContextoOrganizacion::class)->sistemasDelAlcance();

            if ($sistemas !== null) {
                $consulta->getModel()->acotarAlAlcance($consulta, $sistemas);
            }
        });
    }

    /**
     * @param  Builder<static>  $consulta
     * @param  list<int>  $sistemas
     */
    public function acotarAlAlcance(Builder $consulta, array $sistemas): void
    {
        $consulta->whereIn($this->qualifyColumn('sistema_id'), $sistemas);
    }
}
