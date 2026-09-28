<?php

declare(strict_types=1);

namespace App\Domain\Copia;

use App\Domain\Copia\Excepciones\CopiaInvalida;

/**
 * Cifra y descifra un fichero entero, por trozos, con la clave de las copias.
 *
 * XChaCha20-Poly1305 en modo *secretstream* de libsodium, que viene con PHP.
 * No hace falta ninguna dependencia, y el modo resuelve lo que un cifrado de
 * bloque suelto no resuelve: cada trozo va autenticado y el último lleva una
 * marca de final, así que una copia **truncada** —la subida que se cortó a la
 * mitad— no descifra y se nota, en vez de restaurar media base sin avisar.
 *
 * Por trozos porque el volcado de un año de evidencias no cabe en memoria.
 */
final class CifradoDeCopias
{
    private const TROZO = 1024 * 1024;

    public function cifrar(string $origen, string $destino): void
    {
        $clave = $this->clave();
        [$estado, $cabecera] = sodium_crypto_secretstream_xchacha20poly1305_init_push($clave);

        $entrada = $this->abrir($origen, 'rb');
        $salida = $this->abrir($destino, 'wb');

        try {
            fwrite($salida, $cabecera);

            do {
                $trozo = (string) fread($entrada, self::TROZO);
                $final = feof($entrada);

                fwrite($salida, sodium_crypto_secretstream_xchacha20poly1305_push(
                    $estado,
                    $trozo,
                    '',
                    $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE,
                ));
            } while (! $final);
        } finally {
            fclose($entrada);
            fclose($salida);
            sodium_memzero($clave);
        }
    }

    public function descifrar(string $origen, string $destino): void
    {
        $clave = $this->clave();
        $entrada = $this->abrir($origen, 'rb');
        $salida = $this->abrir($destino, 'wb');

        try {
            $cabecera = (string) fread($entrada, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);

            if (strlen($cabecera) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
                throw CopiaInvalida::descifradoFallido('el fichero es más corto que su cabecera');
            }

            $estado = sodium_crypto_secretstream_xchacha20poly1305_init_pull($cabecera, $clave);
            $final = false;

            while (! $final) {
                $trozo = (string) fread($entrada, self::TROZO + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);

                if ($trozo === '') {
                    throw CopiaInvalida::descifradoFallido('se acaba antes de su marca de final, así que está truncada');
                }

                $resultado = sodium_crypto_secretstream_xchacha20poly1305_pull($estado, $trozo);

                if ($resultado === false) {
                    throw CopiaInvalida::descifradoFallido('un trozo no supera la autenticación');
                }

                [$claro, $etiqueta] = $resultado;
                fwrite($salida, $claro);
                $final = $etiqueta === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }

            if (fread($entrada, 1) !== '') {
                throw CopiaInvalida::descifradoFallido('sigue habiendo datos después de su marca de final');
            }
        } finally {
            fclose($entrada);
            fclose($salida);
            sodium_memzero($clave);
        }
    }

    private function clave(): string
    {
        $configurada = config('copias.clave');

        if (! is_string($configurada) || $configurada === '') {
            throw CopiaInvalida::sinClave();
        }

        $clave = base64_decode($configurada, true);

        if ($clave === false || strlen($clave) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw CopiaInvalida::claveMalFormada();
        }

        return $clave;
    }

    /**
     * @return resource
     */
    private function abrir(string $ruta, string $modo)
    {
        $recurso = fopen($ruta, $modo);

        if ($recurso === false) {
            throw CopiaInvalida::procesoFallido('fopen', "no se pudo abrir {$ruta}");
        }

        return $recurso;
    }
}
