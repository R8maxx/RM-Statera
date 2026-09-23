<?php

declare(strict_types=1);

namespace App\Domain\Continuidad\Excepciones;

use DomainException;

/**
 * Un BIA sobre un activo que no es un servicio.
 *
 * Un BIA valora el impacto de que un **servicio** deje de estar disponible; un
 * servidor o un disco no tienen usuarios esperando a que respondan, tienen
 * servicios que dependen de ellos y que sí los tienen. Registrar un BIA sobre
 * cualquier otro `TipoActivo` mezclaría dos preguntas distintas —qué tan grave
 * es que se caiga un servicio, y qué tan grave es que falle la pieza que lo
 * sostiene— y la segunda ya la contesta la valoración efectiva del grafo de
 * activos, no este módulo.
 */
final class ServicioNoValido extends DomainException
{
    public static function noEsServicio(): self
    {
        return new self(
            'Un BIA sólo se registra sobre un activo de tipo «Servicios»: el impacto de un servidor o '
            .'un disco caído lo hereda el servicio que depende de ellos, no se valora aparte.'
        );
    }
}
