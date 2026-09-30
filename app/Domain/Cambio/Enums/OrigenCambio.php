<?php

declare(strict_types=1);

namespace App\Domain\Cambio\Enums;

/**
 * De dónde sale la necesidad del cambio.
 *
 * **Sólo etiqueta, sin clave foránea**, como `OrigenMejora::RevisionDireccion`:
 * un cambio que sale de una revisión por la dirección no «trata» la revisión, y
 * atarlo a ella sería fingir una trazabilidad que no hay.
 */
enum OrigenCambio: string
{
    case Propio = 'propio';
    case RevisionDireccion = 'revision_direccion';
    case Auditoria = 'auditoria';
    case NoConformidad = 'no_conformidad';
    case Contexto = 'contexto';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Propio => 'Iniciativa propia',
            self::RevisionDireccion => 'Revisión por la dirección',
            self::Auditoria => 'Auditoría',
            self::NoConformidad => 'No conformidad',
            self::Contexto => 'Cambio del contexto',
        };
    }
}
