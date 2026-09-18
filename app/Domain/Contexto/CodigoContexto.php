<?php

declare(strict_types=1);

namespace App\Domain\Contexto;

use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Contexto\Models\ParteInteresada;
use Illuminate\Database\Eloquent\Builder;

/**
 * Propone el código de la siguiente cuestión —`CTX-01`— o parte interesada
 * —`PI-01`—.
 *
 * **Una sola clase para los dos**, a diferencia de `CodigoNoConformidad`, porque
 * aquí la regla es idéntica y la única diferencia es el prefijo. Dos clases con el
 * mismo cuerpo y una letra de diferencia es cómo se acaba arreglando el escapado
 * en una y no en la otra.
 *
 * **Propone y no impone**, como el de las no conformidades: una organización que
 * ya tenía su DAFO en una hoja de cálculo llega con códigos propios, y obligarla a
 * renumerar rompe las referencias de las actas donde ya se citan.
 *
 * **Sin año, al contrario que `NC-2026-03`.** Una no conformidad pertenece al año
 * en que se detectó y se cita así; una cuestión del contexto **atraviesa los
 * años** —es justo lo que la hace útil, poder decir «la CTX-07 sigue abierta desde
 * 2024»— y meterle el año la ataría al análisis que la vio nacer. La secuencia es
 * de la organización y no se reinicia nunca.
 *
 * El hueco que deja una retirada **no se reutiliza**: un código repetido en dos
 * actas distintas es peor que un salto en la numeración.
 */
final class CodigoContexto
{
    private const PREFIJO_CUESTION = 'CTX-';

    private const PREFIJO_PARTE = 'PI-';

    public function siguienteCuestion(): string
    {
        return $this->siguiente(CuestionContexto::query(), self::PREFIJO_CUESTION);
    }

    public function siguienteParte(): string
    {
        return $this->siguiente(ParteInteresada::query(), self::PREFIJO_PARTE);
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $consulta
     */
    private function siguiente(Builder $consulta, string $prefijo): string
    {
        /*
         * Sobre el máximo y no sobre el total: con dos borradas, el total daría un
         * código ya usado. Las que no sean numéricas —las que trajo la organización
         * de su hoja— se ignoran, que es lo que hace `NULLIF` con la expresión
         * regular.
         */
        $ultimo = $consulta
            ->where('codigo', 'like', $prefijo.'%')
            ->selectRaw(
                "max(nullif(regexp_replace(substring(codigo from ?), '[^0-9]', '', 'g'), '')::int) as ultimo",
                [strlen($prefijo) + 1],
            )
            ->value('ultimo');

        return $prefijo.sprintf('%02d', ((int) $ultimo) + 1);
    }
}
