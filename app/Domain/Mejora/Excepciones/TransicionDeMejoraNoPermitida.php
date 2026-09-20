<?php

declare(strict_types=1);

namespace App\Domain\Mejora\Excepciones;

use App\Domain\Mejora\Enums\EstadoMejora;
use DomainException;

/**
 * Un salto que la máquina de estados no admite, o uno al que le falta el motivo.
 *
 * El caso que importa es descartar sin decir por qué: la mayoría de las ideas que
 * se apuntan no se hacen, y un registro lleno de descartadas sin explicación no
 * es mejora continua, es un buzón abandonado.
 *
 * Misma forma que `TransicionDeObjetivoNoPermitida`: el motivo se anexa al mensaje
 * en vez de ir en una excepción aparte.
 */
final class TransicionDeMejoraNoPermitida extends DomainException
{
    public function __construct(
        public readonly EstadoMejora $desde,
        public readonly EstadoMejora $hasta,
        string $motivo = '',
    ) {
        $mensaje = "No se permite pasar de [{$desde->value}] a [{$hasta->value}].";

        parent::__construct($motivo === '' ? $mensaje : "{$mensaje} {$motivo}");
    }
}
