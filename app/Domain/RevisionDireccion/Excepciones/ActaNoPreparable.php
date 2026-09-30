<?php

declare(strict_types=1);

namespace App\Domain\RevisionDireccion\Excepciones;

use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use DomainException;

/**
 * La revisión no está en condiciones de tener acta, o no se puede borrar porque
 * ya la tiene.
 *
 * Es una comprobación de dominio con mensaje legible, delante del `CHECK`, del
 * índice único y de la clave foránea de `documentos`, que dirían lo mismo con un
 * error de base de datos. Hermana de `InformeNoPreparable`.
 */
final class ActaNoPreparable extends DomainException
{
    public static function sinAprobar(RevisionDireccion $revision): self
    {
        return new self(sprintf(
            'La revisión %s no está aprobada. El acta imprime las siete entradas tal como se congelaron al firmar: apruébala primero.',
            $revision->codigo,
        ));
    }

    public static function conActa(RevisionDireccion $revision): self
    {
        return new self(sprintf(
            'La revisión %s tiene acta y no se puede eliminar: el acta es el registro de lo que la dirección revisó y decidió.',
            $revision->codigo,
        ));
    }
}
