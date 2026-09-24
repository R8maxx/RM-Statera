<?php

declare(strict_types=1);

namespace App\Domain\Usuario\Excepciones;

use DomainException;

/**
 * Lo que el dominio de cuentas impide, y no sólo avisa.
 *
 * Cada caso lleva su frase entera porque es lo que ve quien lo intenta: un
 * «no permitido» a secas no dice qué hay que hacer antes.
 */
final class OperacionDeCuentaNoPermitida extends DomainException
{
    public static function ultimoResponsable(): self
    {
        return new self(
            'Es la última cuenta activa con el rol de responsable de seguridad. Sin ella nadie podría '
            .'firmar, aceptar riesgos ni dar de alta cuentas: antes hay que dárselo a otra persona.'
        );
    }

    public static function sobreSiMisma(): self
    {
        return new self(
            'No puedes desactivar tu propia cuenta ni quitarte el rol: lo tiene que hacer otro '
            .'responsable de seguridad.'
        );
    }

    public static function auditorSinAlcance(): self
    {
        return new self(
            'Una cuenta de auditor necesita al menos un sistema y una fecha de fin de acceso: el '
            .'auditor externo sólo ve lo que se audita, y sólo mientras dura la auditoría.'
        );
    }

    public static function accesoPasado(): self
    {
        return new self('La fecha de fin de acceso tiene que ser hoy o una fecha futura.');
    }

    public static function yaAceptada(): self
    {
        return new self('Esta cuenta ya aceptó su invitación: no hay nada que reenviar.');
    }

    public static function correoEnUso(): self
    {
        return new self('Ya hay una cuenta con ese correo.');
    }
}
