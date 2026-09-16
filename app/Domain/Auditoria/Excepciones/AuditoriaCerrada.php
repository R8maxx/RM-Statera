<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Excepciones;

use App\Domain\Auditoria\Models\Auditoria;
use RuntimeException;

/**
 * Se ha intentado tocar una auditoría que ya está cerrada.
 *
 * La barrera de verdad es el trigger de PostgreSQL; esto es la que da un mensaje
 * que se entiende. Sin ella el error que sube es el del trigger, que habla de la
 * checklist y no de lo que la persona estaba intentando hacer.
 */
final class AuditoriaCerrada extends RuntimeException
{
    public static function paraChecklist(Auditoria $auditoria): self
    {
        return new self(sprintf(
            'La auditoría %s está cerrada: su checklist no se puede volver a generar. Reábrela primero.',
            $auditoria->codigo,
        ));
    }

    public static function paraHallazgo(Auditoria $auditoria): self
    {
        return new self(sprintf(
            'La auditoría %s está cerrada: no admite hallazgos nuevos. Reábrela primero.',
            $auditoria->codigo,
        ));
    }

    public static function paraPunto(Auditoria $auditoria): self
    {
        return new self(sprintf(
            'La auditoría %s está cerrada: sus resultados ya no se modifican. Reábrela primero.',
            $auditoria->codigo,
        ));
    }
}
