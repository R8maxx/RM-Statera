<?php

declare(strict_types=1);

namespace App\Domain\Contexto\Enums;

/**
 * Si la cuestión juega a favor o en contra.
 *
 * Es el segundo eje del DAFO y, como el ámbito, **se deriva del tipo y no se
 * guarda**: una fortaleza y una oportunidad son favorables, una debilidad y una
 * amenaza adversas, y eso es la definición de la matriz.
 *
 * Existe como enum y no como booleano porque es lo que **decide el color** —
 * `TipoCuestion::tono()` lo consulta— y un booleano llamado `es_favorable`
 * obligaría a escribir la traducción a tono en cada sitio que la pinte.
 */
enum Signo: string
{
    case Favorable = 'favorable';
    case Adverso = 'adverso';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Favorable => 'A favor',
            self::Adverso => 'En contra',
        };
    }
}
