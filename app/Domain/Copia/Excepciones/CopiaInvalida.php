<?php

declare(strict_types=1);

namespace App\Domain\Copia\Excepciones;

use RuntimeException;

/**
 * Lo que impide hacer o comprobar una copia.
 *
 * Cada mensaje dice qué falta y qué hacer, porque lo lee quien mira el correo
 * del planificador a primera hora, no quien escribió el comando.
 */
final class CopiaInvalida extends RuntimeException
{
    public static function sinClave(): self
    {
        return new self(
            'No hay clave de copias: COPIAS_CLAVE está vacía. Genera una con '
            ."php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;' y guárdala también fuera del servidor."
        );
    }

    public static function claveMalFormada(): self
    {
        return new self('COPIAS_CLAVE no son 32 bytes en base64. Genera una nueva; una clave recortada no cifra nada.');
    }

    public static function descifradoFallido(string $motivo): self
    {
        return new self("La copia no se puede descifrar: {$motivo}. O la clave no es la que la cifró, o el fichero se ha alterado.");
    }

    public static function huellaDistinta(string $esperada, string $obtenida): self
    {
        return new self("La copia descargada no es la que se subió: su huella es {$obtenida} y el manifiesto dice {$esperada}.");
    }

    public static function ningunaCopia(): self
    {
        return new self('No hay ninguna copia que verificar. Ejecuta antes copias:hacer.');
    }

    public static function procesoFallido(string $orden, string $salida): self
    {
        return new self("Falló {$orden}: ".trim($salida));
    }
}
