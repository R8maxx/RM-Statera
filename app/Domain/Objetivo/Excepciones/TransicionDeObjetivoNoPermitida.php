<?php

declare(strict_types=1);

namespace App\Domain\Objetivo\Excepciones;

use App\Domain\Objetivo\Enums\EstadoObjetivo;
use DomainException;

/**
 * Un salto que la máquina de estados no admite, uno al que le falta el motivo, o
 * una aprobación sin fecha.
 *
 * Los tres casos que importan: volver a `propuesto` desde algo ya aprobado —decir
 * que un compromiso firmado está sin firmar es reescribir el pasado—, dar un
 * objetivo por no alcanzado sin decir por qué —que es justo lo que la revisión
 * por la dirección va a preguntar— y aprobarlo sin fecha, que la cláusula 6.2
 * exige y que la base rechaza con un error que habla de
 * `objetivos_seguridad_plazo_check` y no de lo que la persona estaba haciendo.
 *
 * Misma forma que `TransicionDeNoConformidadNoPermitida`: el motivo se anexa al
 * mensaje en vez de ir en una excepción aparte, porque para quien lo lee es el
 * mismo problema.
 */
final class TransicionDeObjetivoNoPermitida extends DomainException
{
    public function __construct(
        public readonly EstadoObjetivo $desde,
        public readonly EstadoObjetivo $hasta,
        string $motivo = '',
    ) {
        $mensaje = "No se permite pasar de [{$desde->value}] a [{$hasta->value}].";

        parent::__construct($motivo === '' ? $mensaje : "{$mensaje} {$motivo}");
    }
}
