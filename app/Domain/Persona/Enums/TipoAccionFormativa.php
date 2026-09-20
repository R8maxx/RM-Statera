<?php

declare(strict_types=1);

namespace App\Domain\Persona\Enums;

/**
 * Formación o concienciación, que el ENS separa en dos medidas.
 *
 * **`mp.per.3` y `mp.per.4` son medidas distintas y por eso son dos casos y no un
 * campo libre.** La concienciación es recordar lo que todo el mundo tiene que
 * saber —el correo que llega con un enlace raro— y la formación es enseñar a hacer
 * algo a quien lo tiene que hacer. Una organización puede cumplir una y no la
 * otra, y con un solo valor esa diferencia no se podría enseñar.
 */
enum TipoAccionFormativa: string
{
    case Formacion = 'formacion';
    case Concienciacion = 'concienciacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Formacion => 'Formación',
            self::Concienciacion => 'Concienciación',
        };
    }

    /** La medida del Anexo II que cubre, que es lo que se imprime al lado. */
    public function medida(): string
    {
        return match ($this) {
            self::Formacion => 'mp.per.4',
            self::Concienciacion => 'mp.per.3',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Formacion => 'SearchCheck',
            self::Concienciacion => 'MessageCircle',
        };
    }

    public function tono(): string
    {
        return match ($this) {
            self::Formacion => 'implantado',
            self::Concienciacion => 'planificado',
        };
    }
}
