<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Excepciones;

use App\Domain\Auditoria\Models\Auditoria;
use RuntimeException;

/**
 * La auditoría no está en condiciones de tener informe.
 *
 * Es una comprobación de dominio con mensaje legible, delante del `CHECK` y del
 * índice único de `documentos`, que dirían lo mismo con un error de base de datos.
 */
final class InformeNoPreparable extends RuntimeException
{
    public static function sinCerrar(Auditoria $auditoria): self
    {
        return new self(sprintf(
            'La auditoría %s no está cerrada. El informe recoge lo que quedó congelado al cerrarla: ciérrala primero.',
            $auditoria->codigo,
        ));
    }

    public static function externa(Auditoria $auditoria): self
    {
        return new self(sprintf(
            'La auditoría %s es externa: su informe lo emite la entidad certificadora, no Statera. Guárdalo como evidencia.',
            $auditoria->codigo,
        ));
    }

    public static function conInforme(Auditoria $auditoria): self
    {
        return new self(sprintf(
            'La auditoría %s tiene informe y no se puede eliminar: el informe es el registro de lo que se encontró.',
            $auditoria->codigo,
        ));
    }
}
