<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Enums;

/**
 * Grado de cobertura de un mapeo entre requisitos de marcos distintos.
 *
 * `Parcial` no es un matiz decorativo: significa que el requisito destino cubre
 * solo una parte del origen, y la nota del mapeo dice cuál. Tratarlo como
 * equivalente es la forma más rápida de dar por implantado algo que no lo está.
 */
enum TipoCorrespondencia: string
{
    case Equivalente = 'equivalente';
    case Parcial = 'parcial';
    case Relacionado = 'relacionado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Equivalente => 'Equivalente',
            self::Parcial => 'Cobertura parcial',
            self::Relacionado => 'Relacionado',
        };
    }

    /**
     * Si lo hecho en el otro requisito vale como prueba de éste sin más.
     *
     * Sólo la equivalencia lo permite. Dar por buena una cobertura parcial es
     * la forma más rápida de dar por implantado algo que no lo está, así que la
     * ficha lo dice en vez de dejarlo a interpretación.
     */
    public function cubreDelTodo(): bool
    {
        return $this === self::Equivalente;
    }
}
