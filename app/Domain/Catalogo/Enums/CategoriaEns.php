<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Enums;

/**
 * Categoría del sistema según el Anexo I del ENS.
 *
 * Se corresponde 1:1 con los niveles de las dimensiones de seguridad, y por eso
 * la matriz de aplicabilidad usa una sola columna para ambas lecturas: cuando
 * una medida está modulada por el nivel de una dimensión concreta, `basica` se
 * lee como nivel bajo, `media` como medio y `alta` como alto.
 */
enum CategoriaEns: string
{
    case Basica = 'basica';
    case Media = 'media';
    case Alta = 'alta';

    /**
     * Orden de menor a mayor exigencia. La categoría de un sistema es el máximo
     * de las cinco dimensiones.
     */
    public function peso(): int
    {
        return match ($this) {
            self::Basica => 1,
            self::Media => 2,
            self::Alta => 3,
        };
    }

    public function alcanza(self $minima): bool
    {
        return $this->peso() >= $minima->peso();
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Basica => 'Básica',
            self::Media => 'Media',
            self::Alta => 'Alta',
        };
    }
}
