<?php

declare(strict_types=1);

namespace App\Domain\Objetivo;

use App\Domain\Objetivo\Models\Objetivo;
use Illuminate\Support\Carbon;

/**
 * Propone el código del siguiente objetivo del año: `OBJ-2026-01`.
 *
 * **Propone y no impone**, igual que `CodigoNoConformidad`: una organización que
 * ya llevaba sus objetivos en un acta llega con su propia numeración, y obligarla
 * a renumerar rompe las referencias de las actas anteriores. Es sólo el valor de
 * partida del formulario.
 *
 * Numera **por año y por organización**, que es como se citan en una revisión por
 * la dirección —«el OBJ-2026-02»— y lo que el índice único de la tabla garantiza.
 * Cuenta sobre el máximo en vez de guardar un contador, porque un contador en
 * tabla se desincroniza en cuanto alguien edita un código a mano.
 *
 * El hueco que deja un objetivo retirado **no se reutiliza**: un salto en la
 * numeración es una pregunta del auditor, y un código repetido en dos actas es
 * peor.
 */
final class CodigoObjetivo
{
    public function siguiente(?Carbon $fecha = null): string
    {
        $anio = ($fecha ?? Carbon::today())->year;
        $prefijo = sprintf('OBJ-%d-', $anio);

        /*
         * Se cuenta sobre el máximo y no sobre el total: con dos borradas, el
         * total daría un código ya usado. `substring` sobre el sufijo, y las que
         * no sean numéricas —las que trajo la organización de su hoja— se
         * ignoran, que es lo que hace `NULLIF` con la expresión regular.
         *
         * **El `::int` del parámetro no es adorno.** PDO manda el binding como
         * texto, y `substring(x from '10')` es la forma SQL estándar de
         * `substring(string from pattern)`: PostgreSQL lo lee como una expresión
         * regular, no encuentra nada y devuelve NULL. El resultado era que el
         * máximo salía siempre nulo y **todos los códigos propuestos eran el
         * `-01`**, que a partir del segundo registro del año choca con el índice
         * único y sale por pantalla como «el código ya está usado». No lo
         * cazaba ningún test porque el que había sólo comprobaba el primero.
         */
        $ultimo = Objetivo::query()
            ->where('codigo', 'like', $prefijo.'%')
            ->selectRaw("max(nullif(regexp_replace(substring(codigo from ?::int), '[^0-9]', '', 'g'), '')::int) as ultimo", [strlen($prefijo) + 1])
            ->value('ultimo');

        return $prefijo.sprintf('%02d', ((int) $ultimo) + 1);
    }
}
