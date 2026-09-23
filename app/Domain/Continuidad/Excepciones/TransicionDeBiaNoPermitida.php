<?php

declare(strict_types=1);

namespace App\Domain\Continuidad\Excepciones;

use App\Domain\Continuidad\Enums\EstadoBia;
use DomainException;

/**
 * Un paso que la máquina de estados de un BIA no admite, o uno que sí lo
 * admite pero exige decir por qué.
 *
 * **Salir de `aprobado`, ya sea a `obsoleto` o de vuelta a `borrador` por
 * decisión de alguien, exige motivo.** Un BIA aprobado es lo que el resto de
 * la organización da por vigente al planificar la continuidad; dejarlo de
 * estarlo sin una nota es dejar sin explicar por qué el número en el que
 * todos confiaban ya no vale. `EditarBia` no pasa por esta guarda para su
 * propio retorno a borrador: ahí el motivo lo pone el sistema, porque la causa
 * es la propia edición.
 */
final class TransicionDeBiaNoPermitida extends DomainException
{
    public static function entre(EstadoBia $desde, EstadoBia $hasta): self
    {
        return new self(sprintf(
            'Un BIA «%s» no puede pasar a «%s».',
            $desde->etiqueta(),
            $hasta->etiqueta(),
        ));
    }

    public static function sinMotivo(EstadoBia $destino): self
    {
        return new self(sprintf(
            'Pasar a «%s» exige escribir por qué: es lo que explica que un BIA que la organización daba '
            .'por vigente haya dejado de estarlo.',
            $destino->etiqueta(),
        ));
    }
}
