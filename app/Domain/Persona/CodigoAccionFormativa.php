<?php

declare(strict_types=1);

namespace App\Domain\Persona;

use App\Domain\Persona\Models\AccionFormativa;
use Illuminate\Support\Carbon;

/**
 * Propone el código de la siguiente sesión del año: `FOR-2026-01`.
 *
 * **Con año, al revés que `CodigoPersona`**, y la asimetría es real: una persona
 * no pertenece a un ejercicio y un plan de formación sí — «la formación de 2026»
 * es literalmente lo que se presenta en la revisión por la dirección.
 *
 * El `::int` del parámetro no es adorno: sin él, PDO manda el binding como texto y
 * PostgreSQL lee `substring(x from '10')` como la forma con expresión regular.
 * Ver `CodigoNoConformidad`, que es donde mordió.
 */
final class CodigoAccionFormativa
{
    public function siguiente(?Carbon $fecha = null): string
    {
        $anio = ($fecha ?? Carbon::today())->year;
        $prefijo = sprintf('FOR-%d-', $anio);

        $ultimo = AccionFormativa::query()
            ->where('codigo', 'like', $prefijo.'%')
            ->selectRaw("max(nullif(regexp_replace(substring(codigo from ?::int), '[^0-9]', '', 'g'), '')::int) as ultimo", [strlen($prefijo) + 1])
            ->value('ultimo');

        return $prefijo.sprintf('%02d', ((int) $ultimo) + 1);
    }
}
