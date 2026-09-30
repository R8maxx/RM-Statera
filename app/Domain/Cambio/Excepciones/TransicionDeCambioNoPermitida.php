<?php

declare(strict_types=1);

namespace App\Domain\Cambio\Excepciones;

use App\Domain\Cambio\Enums\EstadoCambio;
use DomainException;

/**
 * Un salto que la máquina de estados no admite, o uno al que le falta algo: el
 * plazo al aprobar, o la nota al descartar, revisar o reabrir.
 *
 * Misma forma que `TransicionDeObjetivoNoPermitida`: el motivo se anexa al mensaje
 * en vez de ir en una excepción aparte.
 */
final class TransicionDeCambioNoPermitida extends DomainException
{
    public function __construct(
        public readonly EstadoCambio $desde,
        public readonly EstadoCambio $hasta,
        string $motivo = '',
    ) {
        $mensaje = "No se permite pasar de [{$desde->value}] a [{$hasta->value}].";

        parent::__construct($motivo === '' ? $mensaje : "{$mensaje} {$motivo}");
    }
}
