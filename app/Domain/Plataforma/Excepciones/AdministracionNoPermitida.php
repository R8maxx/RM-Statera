<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Excepciones;

use DomainException;

/**
 * Lo que impide cambiar o retirar a quien administra la plataforma (punto 49).
 */
final class AdministracionNoPermitida extends DomainException
{
    public static function sobreSiMismo(): self
    {
        return new self('No puedes cambiar tu propio perfil ni retirarte a ti mismo: lo tiene que hacer otra persona de Administración.');
    }

    public static function ultimoAdministrador(): self
    {
        return new self(
            'Es la última cuenta activa con perfil de Administración. Sin ella nadie podría dar de alta '
            .'administradores, rescatar cuentas ni ver la salud del servicio: antes hay que dárselo a otra persona.'
        );
    }
}
