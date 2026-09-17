<?php

declare(strict_types=1);

namespace App\Domain\NoConformidad;

use App\Domain\NoConformidad\Models\NoConformidad;
use Illuminate\Support\Carbon;

/**
 * Propone el código de la siguiente no conformidad del año: `NC-2026-01`.
 *
 * **Propone y no impone**: el código es editable en el formulario, porque una
 * organización que ya llevaba su registro en una hoja de cálculo llega con
 * códigos propios y obligarla a renumerar rompe las referencias de sus actas.
 * Sólo se usa como valor de partida, y es lo único que hace falta para que abrir
 * una no conformidad desde un hallazgo no pida escribir un código a mano.
 *
 * Numera **por año de detección y por organización**, que es como se citan en un
 * acta —«la NC-2026-03»— y lo que el índice único de la tabla garantiza. Cuenta
 * las que ya hay en vez de guardar un contador: un contador en tabla se
 * desincroniza en cuanto alguien edita un código a mano, y aquí la fuente de
 * verdad es la columna.
 *
 * El hueco que deja una anulada **no se reutiliza**, por lo mismo que un número
 * de documento rechazado no se reaprovecha: un salto en la numeración es una
 * pregunta del auditor, y un código repetido en dos actas distintas es peor.
 */
final class CodigoNoConformidad
{
    public function siguiente(?Carbon $fecha = null): string
    {
        $anio = ($fecha ?? Carbon::today())->year;
        $prefijo = sprintf('NC-%d-', $anio);

        /*
         * Se cuenta sobre el máximo y no sobre el total: con dos borradas, el
         * total daría un código ya usado. `substring` sobre el sufijo, y las que
         * no sean numéricas —las que trajo la organización de su hoja— se
         * ignoran, que es lo que hace `NULLIF` con la expresión regular.
         */
        $ultimo = NoConformidad::query()
            ->where('codigo', 'like', $prefijo.'%')
            ->selectRaw("max(nullif(regexp_replace(substring(codigo from ?), '[^0-9]', '', 'g'), '')::int) as ultimo", [strlen($prefijo) + 1])
            ->value('ultimo');

        return $prefijo.sprintf('%02d', ((int) $ultimo) + 1);
    }
}
