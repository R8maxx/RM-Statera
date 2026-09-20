<?php

declare(strict_types=1);

namespace App\Domain\Mejora;

use App\Domain\Mejora\Models\Mejora;
use Illuminate\Support\Carbon;

/**
 * Propone el código de la siguiente mejora del año: `OM-2026-01`.
 *
 * **Propone y no impone**, igual que `CodigoNoConformidad` y `CodigoObjetivo`.
 *
 * El `::int` del parámetro no es adorno: sin él, PDO manda el binding como texto y
 * PostgreSQL lee `substring(x from '9')` como la forma con expresión regular,
 * devuelve NULL y **todos los códigos propuestos serían el `-01`**. Es el fallo
 * que traía `CodigoNoConformidad` y que se destapó al copiar esta clase.
 */
final class CodigoMejora
{
    public function siguiente(?Carbon $fecha = null): string
    {
        $anio = ($fecha ?? Carbon::today())->year;
        $prefijo = sprintf('OM-%d-', $anio);

        $ultimo = Mejora::query()
            ->where('codigo', 'like', $prefijo.'%')
            ->selectRaw("max(nullif(regexp_replace(substring(codigo from ?::int), '[^0-9]', '', 'g'), '')::int) as ultimo", [strlen($prefijo) + 1])
            ->value('ultimo');

        return $prefijo.sprintf('%02d', ((int) $ultimo) + 1);
    }
}
