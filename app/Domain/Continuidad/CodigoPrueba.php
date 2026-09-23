<?php

declare(strict_types=1);

namespace App\Domain\Continuidad;

use App\Domain\Continuidad\Models\PruebaContinuidad;
use Illuminate\Support\Carbon;

/**
 * Propone el código de la siguiente prueba de continuidad del año: `PC-2026-01`.
 *
 * Copia exacta de `CodigoIncidente` con el prefijo `PC-`: **propone y no
 * impone**, y el `::int` del parámetro no es adorno —sin él, PDO manda el
 * binding como texto, PostgreSQL lee la resta como la forma con expresión
 * regular de `substring()`, devuelve `NULL` y todos los códigos propuestos
 * serían el `-01`—.
 */
final class CodigoPrueba
{
    public function siguiente(?Carbon $fecha = null): string
    {
        $anio = ($fecha ?? Carbon::today())->year;
        $prefijo = sprintf('PC-%d-', $anio);

        $ultimo = PruebaContinuidad::query()
            ->where('codigo', 'like', $prefijo.'%')
            ->selectRaw("max(nullif(regexp_replace(substring(codigo from ?::int), '[^0-9]', '', 'g'), '')::int) as ultimo", [strlen($prefijo) + 1])
            ->value('ultimo');

        return $prefijo.sprintf('%02d', ((int) $ultimo) + 1);
    }
}
