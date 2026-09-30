<?php

declare(strict_types=1);

namespace App\Domain\Cambio;

use App\Domain\Cambio\Models\CambioSgsi;
use Illuminate\Support\Carbon;

/**
 * Propone el código del siguiente cambio del año: `CS-2026-01`.
 *
 * **Propone y no impone**, como `CodigoMejora`. Y con el mismo `?::int` en el
 * desplazamiento: sin él, PDO lo manda como texto, PostgreSQL lee la forma de
 * `substring` con expresión regular y todos los códigos propuestos saldrían
 * `-01`.
 */
final class CodigoCambio
{
    public function siguiente(?Carbon $fecha = null): string
    {
        $anio = ($fecha ?? Carbon::today())->year;
        $prefijo = sprintf('CS-%d-', $anio);

        $ultimo = CambioSgsi::query()
            ->where('codigo', 'like', $prefijo.'%')
            ->selectRaw("max(nullif(regexp_replace(substring(codigo from ?::int), '[^0-9]', '', 'g'), '')::int) as ultimo", [strlen($prefijo) + 1])
            ->value('ultimo');

        return $prefijo.sprintf('%02d', ((int) $ultimo) + 1);
    }
}
