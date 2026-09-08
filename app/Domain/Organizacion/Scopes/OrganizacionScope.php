<?php

declare(strict_types=1);

namespace App\Domain\Organizacion\Scopes;

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Segunda capa del aislamiento: filtra toda consulta de Eloquent por la
 * organización activa.
 *
 * Cuando no hay contexto no devuelve nada. Denegar por defecto es lo mismo que
 * hace la política de RLS, y por el mismo motivo: una consulta sin tenant
 * declarado es un error de programación, no una consulta global.
 *
 * Quitarlo con `withoutGlobalScopes()` está prohibido fuera de comandos de
 * mantenimiento explícitos, y aunque se quite queda la capa de PostgreSQL.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
final class OrganizacionScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $contexto = app(ContextoOrganizacion::class);

        if ($contexto->enMantenimiento()) {
            return;
        }

        $columna = $model->qualifyColumn('organizacion_id');

        if (! $contexto->hayContexto()) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($columna, $contexto->id());
    }
}
