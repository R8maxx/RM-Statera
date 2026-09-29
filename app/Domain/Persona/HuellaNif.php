<?php

declare(strict_types=1);

namespace App\Domain\Persona;

use RuntimeException;

/**
 * El índice ciego del NIF: una huella con la que buscar y exigir unicidad sin
 * guardar el documento en claro (punto 35).
 *
 * El NIF va cifrado, y un cifrado que sirve de algo da un texto distinto cada
 * vez: el índice único por organización dejaría de ver dos iguales. La huella
 * es un HMAC-SHA256 del NIF ya normalizado, con una clave que **no** es
 * `APP_KEY`. Si lo fuera, rotar la clave de la aplicación rompería la unicidad
 * sin avisar; y un SHA-256 sin clave se deshace probando los cien millones de
 * DNI posibles en una tarde.
 *
 * La huella permite saber si dos personas tienen el mismo documento, y nada
 * más: sin la clave no dice cuál es.
 */
final class HuellaNif
{
    public static function de(?string $nif): ?string
    {
        if ($nif === null || $nif === '') {
            return null;
        }

        return hash_hmac('sha256', self::normalizar($nif), self::clave());
    }

    /**
     * Mayúsculas y sin separadores. Es la misma grafía que impone
     * `GuardarPersonaRequest`, repetida aquí porque la huella se calcula
     * también desde el seeder y las factories, que no pasan por el formulario.
     */
    public static function normalizar(string $nif): string
    {
        return mb_strtoupper(preg_replace('/[\s-]+/u', '', $nif) ?? $nif);
    }

    private static function clave(): string
    {
        $clave = config('seguridad.clave_indice_ciego');

        if (! is_string($clave) || $clave === '') {
            throw new RuntimeException(
                'Falta CLAVE_INDICE_CIEGO: sin ella no se puede comprobar si un NIF está repetido. '
                ."Genera una con php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'."
            );
        }

        return $clave;
    }
}
