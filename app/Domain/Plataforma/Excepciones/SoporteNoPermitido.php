<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Excepciones;

use DomainException;

/**
 * Lo que impide abrir una ventana de soporte o entrar por ella (punto 44).
 */
final class SoporteNoPermitido extends DomainException
{
    public static function ventanaCerrada(): self
    {
        return new self(
            'Esta organización no tiene abierto el acceso de soporte. Lo abre su responsable de seguridad '
            .'desde la ficha de la organización, y sólo mientras dura se puede entrar.'
        );
    }

    public static function esLaSuya(): self
    {
        return new self('Es tu propia organización: ya entras en ella con tu rol, no hace falta el acceso de soporte.');
    }

    public static function duracionFueraDeRango(): self
    {
        return new self('El acceso de soporte dura entre una hora y siete días.');
    }
}
