<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Excepciones;

use RuntimeException;

/**
 * El fichero de catálogo no se puede importar. Se lanza ANTES de escribir nada:
 * un YAML malformado o incoherente no debe dejar el catálogo a medias.
 */
final class CatalogoInvalido extends RuntimeException
{
    /** @param list<string> $errores */
    public function __construct(public readonly string $fichero, public readonly array $errores)
    {
        parent::__construct(sprintf(
            "El catálogo [%s] no es válido:\n  - %s",
            $fichero,
            implode("\n  - ", $errores),
        ));
    }
}
