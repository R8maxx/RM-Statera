<?php

declare(strict_types=1);

namespace App\Domain\Categorizacion\Enums;

use App\Domain\Catalogo\Enums\CategoriaEns;

/**
 * Nivel al que se valora una dimensión de seguridad, según el Anexo I del ENS.
 *
 * `Na` no es "bajo": significa que la dimensión no es de aplicación al sistema.
 * Un sistema cuyas cinco dimensiones son `Na` queda fuera del ámbito del ENS, no
 * en categoría básica, y el motor devuelve conjunto vacío.
 */
enum NivelDimension: string
{
    case Na = 'na';
    case Bajo = 'bajo';
    case Medio = 'medio';
    case Alto = 'alto';

    public function peso(): int
    {
        return match ($this) {
            self::Na => 0,
            self::Bajo => 1,
            self::Medio => 2,
            self::Alto => 3,
        };
    }

    /**
     * Único punto del sistema donde nivel de dimensión y categoría se traducen.
     *
     * La correspondencia es 1:1 en el ENS y por eso `aplicabilidad_ens` usa una
     * sola columna para las dos lecturas: cuando una medida está modulada por el
     * nivel de una dimensión concreta, la fila `basica` se lee como nivel bajo,
     * `media` como medio y `alta` como alto.
     */
    public function aCategoria(): ?CategoriaEns
    {
        return match ($this) {
            self::Na => null,
            self::Bajo => CategoriaEns::Basica,
            self::Medio => CategoriaEns::Media,
            self::Alto => CategoriaEns::Alta,
        };
    }

    public function alcanza(self $minimo): bool
    {
        return $this->peso() >= $minimo->peso();
    }
}
