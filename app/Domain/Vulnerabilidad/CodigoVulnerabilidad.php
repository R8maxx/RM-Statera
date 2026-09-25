<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad;

use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use Illuminate\Support\Carbon;

/** El siguiente código libre del año, `VUL-2026-01`. Una propuesta: se puede cambiar. */
final class CodigoVulnerabilidad
{
    public function siguiente(?Carbon $fecha = null): string
    {
        $prefijo = sprintf('VUL-%d-', ($fecha ?? Carbon::today())->year);

        $ultimo = Vulnerabilidad::query()
            ->where('codigo', 'like', $prefijo.'%')
            ->selectRaw("max(nullif(regexp_replace(substring(codigo from ?::int), '[^0-9]', '', 'g'), '')::int) as ultimo", [strlen($prefijo) + 1])
            ->value('ultimo');

        return $prefijo.sprintf('%02d', ((int) $ultimo) + 1);
    }
}
