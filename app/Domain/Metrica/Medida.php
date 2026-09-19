<?php

declare(strict_types=1);

namespace App\Domain\Metrica;

/**
 * Una cifra recién tomada, antes de convertirse en fila.
 *
 * Lleva el denominador desde el primer momento y no se lo añade nadie después:
 * es el tercer principio del producto —toda cifra viaja con su denominador— y la
 * única forma de que no se pierda por el camino entre quien la calcula y quien
 * la pinta. «43 sin cifrar» es una urgencia sobre 4 y un martes sobre 307.
 *
 * Una media no tiene numerador —no es una fracción, es un promedio—, así que los
 * dos son opcionales; lo que no se admite es uno sin el otro, y eso lo impone
 * además un `CHECK` de la tabla.
 */
final readonly class Medida
{
    private function __construct(
        public float $valor,
        public ?int $numerador,
        public ?int $denominador,
    ) {}

    /**
     * Una fracción: el porcentaje sale de ella y no al revés.
     *
     * Con denominador cero no hay porcentaje que calcular, y **los dos extremos
     * se van a nulo**: «de nada, nada» no es «cero de cero», y un denominador a
     * cero impreso al lado de la cifra se lee como un error. El valor queda en
     * cero porque la fila necesita uno, y el par vacío es lo que delata que no
     * había de dónde contar.
     */
    public static function fraccion(int $numerador, int $denominador): self
    {
        if ($denominador === 0) {
            return new self(valor: 0.0, numerador: null, denominador: null);
        }

        return new self(
            valor: round($numerador * 100 / $denominador, 2),
            numerador: $numerador,
            denominador: $denominador,
        );
    }

    /** Un recuento, con el total del que sale para poder leerlo. */
    public static function recuento(int $cuantos, ?int $de = null): self
    {
        if ($de === null || $de === 0) {
            return new self(valor: (float) $cuantos, numerador: null, denominador: null);
        }

        return new self(valor: (float) $cuantos, numerador: $cuantos, denominador: $de);
    }

    /** Un promedio, con cuántos casos lo sostienen. */
    public static function promedio(?float $media, int $sobre): self
    {
        return new self(
            // Sin ningún caso la media no es cero: es que no se sabe. Se registra
            // como cero y el denominador a nulo lo delata, que es lo mismo que
            // hace `ResumenCumplimiento::madurez()` con `media => null`.
            valor: $media ?? 0.0,
            numerador: null,
            denominador: $sobre === 0 ? null : $sobre,
        );
    }
}
