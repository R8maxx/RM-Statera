<?php

declare(strict_types=1);

namespace App\Domain\Obligacion;

use App\Domain\Obligacion\Models\Compromiso;
use Illuminate\Support\Carbon;

/**
 * Propone el código del siguiente compromiso del año: `OBL-2026-01`.
 *
 * Mismo contrato que `CodigoObjetivo` y `CodigoNoConformidad`, incluida la razón:
 * **propone y no impone**, numera por año y por organización, cuenta sobre el
 * máximo y no reutiliza el hueco que deja uno retirado.
 *
 * El `::int` del parámetro tampoco es adorno aquí, y merece la pena repetirlo
 * porque el fallo es invisible: PDO manda el binding como texto y
 * `substring(x from '10')` es la forma SQL de `substring(string from pattern)`,
 * así que PostgreSQL lo leería como expresión regular, no encontraría nada, el
 * máximo saldría nulo y **todos los códigos propuestos serían el `-01`**.
 */
final class CodigoCompromiso
{
    public function siguiente(?Carbon $fecha = null): string
    {
        $anio = ($fecha ?? Carbon::today())->year;
        $prefijo = sprintf('OBL-%d-', $anio);

        $ultimo = Compromiso::query()
            ->where('codigo', 'like', $prefijo.'%')
            ->selectRaw("max(nullif(regexp_replace(substring(codigo from ?::int), '[^0-9]', '', 'g'), '')::int) as ultimo", [strlen($prefijo) + 1])
            ->value('ultimo');

        return $prefijo.sprintf('%02d', ((int) $ultimo) + 1);
    }
}
