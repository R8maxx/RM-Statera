<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Excepciones;

use DomainException;

/**
 * Lo que impide pedir o resolver un rescate de cuenta (punto 52).
 */
final class RescateNoPermitido extends DomainException
{
    public static function sinCapacidad(): self
    {
        return new self('Tu perfil no permite rescatar cuentas: lo hace Administración.');
    }

    public static function cuentaAjena(): self
    {
        return new self('Esa cuenta no es de esta organización.');
    }

    public static function cuentaDePlataforma(): self
    {
        return new self(
            'Es una cuenta de quien administra la plataforma. Su segundo factor y su papel no se rescatan desde un '
            .'cliente: los gestiona Administración en la lista de administradores.'
        );
    }

    public static function yaEsResponsable(): self
    {
        return new self('Esa cuenta ya es responsable de seguridad.');
    }

    public static function sinDatosDelResponsable(): self
    {
        return new self('Hace falta elegir una cuenta de la organización o dar el nombre y el correo del nuevo responsable.');
    }

    public static function yaResuelta(): self
    {
        return new self('Esta solicitud ya no está pendiente: se ejecutó, se rechazó o caducó.');
    }

    public static function mismaPersona(): self
    {
        return new self(
            'La ejecuta otra persona de Administración, no quien la pidió: es la segunda mirada que protege a la '
            .'cuenta de quien se haga pasar por su dueño.'
        );
    }
}
