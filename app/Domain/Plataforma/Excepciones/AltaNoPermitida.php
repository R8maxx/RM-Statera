<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Excepciones;

use DomainException;

/**
 * Lo que impide dar de alta una organización. Cada caso lleva la frase entera,
 * porque es lo que lee quien lo intenta.
 */
final class AltaNoPermitida extends DomainException
{
    public static function catalogoSinImportar(): self
    {
        return new self(
            'El catálogo normativo no está importado: sin el ENS no hay medidas que derivar. '
            .'Ejecuta `php artisan catalogo:importar` antes de dar de alta organizaciones.'
        );
    }
}
