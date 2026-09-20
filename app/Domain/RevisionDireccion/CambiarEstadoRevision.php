<?php

declare(strict_types=1);

namespace App\Domain\RevisionDireccion;

use App\Domain\RevisionDireccion\Enums\EstadoRevision;
use App\Domain\RevisionDireccion\Excepciones\TransicionDeRevisionNoPermitida;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;

/**
 * Mueve una revisión entre `planificada` y `en_curso`, y la reabre desde
 * `aprobada`.
 *
 * **Aprobar NO pasa por aquí**: eso lo hace `AprobarRevision`, porque no es un
 * cambio de estado sino el acto que congela las siete entradas y estampa la firma.
 * Separarlos es lo mismo que hace el § 4.1 con `AprobarAnalisis`, y evita que una
 * ruta genérica de transición pueda firmar un acta sin instantánea — que el
 * `CHECK` rechazaría con un error que no menciona la palabra «entradas».
 *
 * **La guarda está aquí aunque el trigger también lo impida**, por el mismo
 * motivo que se escribió para el cierre de una auditoría: un `update` que el
 * trigger rechaza sube como `QueryException` sin capturar, el usuario ve el 500
 * genérico y el mensaje de la base —sin tildes, porque es SQL— no lo lee nadie.
 *
 * **Sin histórico de transiciones**, a diferencia de tareas, no conformidades,
 * objetivos y mejoras. No es un descuido: lo que el auditor pregunta de una
 * revisión no es desde cuándo está en curso, es **qué se revisó y qué se decidió**,
 * y eso lo contesta la instantánea con su firma y su fecha. Un registro de
 * transiciones aquí guardaría el ir y venir de una reunión que se aplaza, que no
 * es una pregunta que nadie haga.
 */
final class CambiarEstadoRevision
{
    /**
     * @throws TransicionDeRevisionNoPermitida
     */
    public function __invoke(RevisionDireccion $revision, EstadoRevision $nuevo): RevisionDireccion
    {
        $actual = $revision->estado;

        if ($actual === $nuevo) {
            return $revision;
        }

        if ($nuevo === EstadoRevision::Aprobada || ! $actual->permite($nuevo)) {
            throw new TransicionDeRevisionNoPermitida($actual, $nuevo);
        }

        $revision->update(['estado' => $nuevo->value]);

        return $revision->refresh();
    }
}
