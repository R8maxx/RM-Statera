<?php

declare(strict_types=1);

namespace App\Domain\Comunicacion;

use App\Domain\Comunicacion\Models\ComunicacionPrevista;

/**
 * Propone el código de la siguiente línea del plan: `PC-01`.
 *
 * **Sin año**, a diferencia de mejoras y cambios: una línea del plan no nace en
 * un año y se cierra en él, dura mientras la organización la mantenga. Con el
 * `?::int` de `CodigoMejora` en el desplazamiento, por el mismo motivo.
 */
final class CodigoComunicacionPrevista
{
    private const PREFIJO = 'PC-';

    public function siguiente(): string
    {
        $ultimo = ComunicacionPrevista::query()
            ->where('codigo', 'like', self::PREFIJO.'%')
            ->selectRaw("max(nullif(regexp_replace(substring(codigo from ?::int), '[^0-9]', '', 'g'), '')::int) as ultimo", [strlen(self::PREFIJO) + 1])
            ->value('ultimo');

        return self::PREFIJO.sprintf('%02d', ((int) $ultimo) + 1);
    }
}
