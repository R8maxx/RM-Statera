<?php

declare(strict_types=1);

namespace App\Domain\NoConformidad\Excepciones;

use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use DomainException;

/**
 * Un salto que la máquina de estados no admite, o uno que le falta el motivo.
 *
 * Los dos casos que importan: volver a `abierta` desde algo ya tratado —decir que
 * una no conformidad que se corrigió está sin coger es reescribir el pasado— y
 * anular sin decir por qué, que deja el hallazgo sin rastro de qué se decidió con
 * él. Misma forma que `TransicionDeTareaNoPermitida`, y el motivo se anexa al
 * mensaje en vez de ir en una excepción aparte: para quien lo lee es el mismo
 * problema.
 */
final class TransicionDeNoConformidadNoPermitida extends DomainException
{
    public function __construct(
        public readonly EstadoNoConformidad $desde,
        public readonly EstadoNoConformidad $hasta,
        string $motivo = '',
    ) {
        $mensaje = "No se permite pasar de [{$desde->value}] a [{$hasta->value}].";

        parent::__construct($motivo === '' ? $mensaje : "{$mensaje} {$motivo}");
    }
}
