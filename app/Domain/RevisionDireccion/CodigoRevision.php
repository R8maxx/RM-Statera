<?php

declare(strict_types=1);

namespace App\Domain\RevisionDireccion;

use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use Illuminate\Support\Carbon;

/**
 * Propone el código de la siguiente revisión del año: `RD-2026-01`.
 *
 * **Propone y no impone**, igual que el resto de generadores de código. La mayoría
 * de organizaciones celebran una al año, así que el `-01` será casi siempre el
 * bueno; el `-02` aparece cuando hay una extraordinaria, que es justo el caso en
 * el que la numeración importa.
 *
 * El `::int` del parámetro no es adorno: sin él, PDO manda el binding como texto y
 * PostgreSQL lee `substring(x from '9')` como la forma con expresión regular. Ver
 * `CodigoNoConformidad`.
 */
final class CodigoRevision
{
    public function siguiente(?Carbon $fecha = null): string
    {
        $anio = ($fecha ?? Carbon::today())->year;
        $prefijo = sprintf('RD-%d-', $anio);

        $ultimo = RevisionDireccion::query()
            ->where('codigo', 'like', $prefijo.'%')
            ->selectRaw("max(nullif(regexp_replace(substring(codigo from ?::int), '[^0-9]', '', 'g'), '')::int) as ultimo", [strlen($prefijo) + 1])
            ->value('ultimo');

        return $prefijo.sprintf('%02d', ((int) $ultimo) + 1);
    }
}
