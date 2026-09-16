<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Excepciones;

use App\Domain\Auditoria\Enums\EstadoAuditoria;
use RuntimeException;

/**
 * Un salto que la máquina de estados no admite.
 *
 * El caso que importa es «cerrada → planificada»: decir que una auditoría que ya
 * se hizo está por hacer es reescribir el pasado. De cerrada sólo se vuelve a
 * `en_curso`, y eso se llama reabrir.
 */
final class TransicionDeAuditoriaNoPermitida extends RuntimeException
{
    public static function de(EstadoAuditoria $desde, EstadoAuditoria $hasta): self
    {
        return new self(sprintf(
            'Una auditoría %s no puede pasar a %s.',
            mb_strtolower($desde->etiqueta()),
            mb_strtolower($hasta->etiqueta()),
        ));
    }
}
