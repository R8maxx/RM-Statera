<?php

declare(strict_types=1);

namespace App\Domain\Obligacion;

use App\Domain\Catalogo\Enums\CategoriaEns;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Obligacion\Models\Obligacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Support\Collection;

/**
 * Qué obligaciones del catálogo tiene sentido proponerle a esta organización.
 *
 * **Propone, no genera.** Ni un observer que las siembre al crear la organización
 * ni un comando que las asuma todas: un compromiso es algo a lo que alguien se
 * obliga, y obligarse por una casilla que no marcó nadie es ponerle a la
 * organización deberes que no ha aceptado. El invariante 4 dice que la
 * aplicabilidad se deriva; esto es lo otro, una decisión, y las decisiones las
 * firma una persona.
 *
 * Tres filtros, y ninguno inventa nada:
 *
 * 1. **El marco.** Una obligación del ENS sólo se propone a quien tenga un sistema
 *    de ese marco. Sin `marco_id` se propone siempre: la revisión por la dirección
 *    la piden los dos con palabras distintas y es la misma reunión.
 * 2. **`leAplicaElEns()`.** Las banderas de la ficha de la organización mandan
 *    sobre el marco del sistema: alguien puede tener el ENS cargado para mirarlo
 *    sin estar sujeto a él, y proponerle el INES sería decirle que tiene que
 *    reportar al CCN.
 * 3. **La categoría mínima**, contra `Sistema::categoria()`, que **se deriva** de
 *    las cinco dimensiones (invariante 4). Se compara contra la categoría más alta
 *    de sus sistemas: si uno de ellos alcanza media, las pruebas de continuidad ya
 *    muerden aunque los otros tres sigan en básica.
 */
final readonly class ObligacionesAplicables
{
    /**
     * Las vigentes que le tocan, sin las que ya tiene asumidas.
     *
     * @return Collection<int, Obligacion>
     */
    public function para(Organizacion $organizacion): Collection
    {
        $asumidas = Compromiso::query()
            ->whereNotNull('obligacion_id')
            ->pluck('obligacion_id')
            ->all();

        return $this->delCatalogo($organizacion)
            ->reject(static fn (Obligacion $obligacion): bool => in_array($obligacion->id, $asumidas, true))
            ->values();
    }

    /**
     * Todas las que le tocan, asumidas o no. Es lo que contesta «¿de qué me
     * tendría que estar ocupando?» sin mirar lo que ya hay.
     *
     * @return Collection<int, Obligacion>
     */
    public function delCatalogo(Organizacion $organizacion): Collection
    {
        /*
         * Los sistemas se traen una vez, con sus valoraciones. `categoria()`
         * recorre cinco filas por sistema y llamarla dentro de un bucle por
         * obligación multiplicaría eso por siete.
         */
        $sistemas = Sistema::query()->with('valoraciones')->get();

        $marcos = $sistemas->pluck('marco_id')->unique()->filter()->all();
        $categoria = $this->categoriaMasAlta($sistemas);
        $leAplicaElEns = $organizacion->leAplicaElEns();

        return Obligacion::query()
            ->vigentes()
            ->with('marco')
            ->orderBy('orden')
            ->get()
            ->filter(function (Obligacion $obligacion) use ($marcos, $categoria, $leAplicaElEns): bool {
                if ($obligacion->marco_id !== null && ! in_array($obligacion->marco_id, $marcos, true)) {
                    return false;
                }

                if ($this->esDelEns($obligacion) && ! $leAplicaElEns) {
                    return false;
                }

                if ($obligacion->categoria_minima === null) {
                    return true;
                }

                return $categoria !== null && $obligacion->exigibleEn($categoria);
            })
            ->values();
    }

    /**
     * La categoría más alta de los sistemas de la organización, o nada si ninguno
     * está valorado todavía.
     *
     * **El máximo y no el mínimo**: una obligación que muerde a partir de media
     * muerde en cuanto un solo sistema llegue ahí, aunque los demás sigan en
     * básica. Cogerlo al revés dejaría sin proponer justo lo del sistema que más
     * lo necesita.
     *
     * @param  Collection<int, Sistema>  $sistemas
     */
    private function categoriaMasAlta(Collection $sistemas): ?CategoriaEns
    {
        $categorias = $sistemas
            ->map(static fn (Sistema $sistema): ?CategoriaEns => $sistema->categoria())
            ->filter()
            ->values();

        if ($categorias->isEmpty()) {
            return null;
        }

        return $categorias->sortByDesc(static fn (CategoriaEns $categoria): int => $categoria->peso())->first();
    }

    /**
     * Si la obligación es del ENS, leído del código del marco y no de una lista
     * de códigos escrita aquí.
     */
    private function esDelEns(Obligacion $obligacion): bool
    {
        return str_starts_with((string) $obligacion->marco?->codigo, 'ENS-');
    }
}
