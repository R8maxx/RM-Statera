<?php

declare(strict_types=1);

namespace App\Domain\Auditoria;

use App\Domain\Auditoria\Enums\TipoHallazgo;
use App\Domain\Auditoria\Excepciones\AuditoriaCerrada;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\AuditoriaPunto;
use App\Domain\Auditoria\Models\Hallazgo;

/**
 * Registrar y retirar hallazgos.
 *
 * **El punto es opcional y ésa es la razón de que esto no viva dentro de la
 * checklist.** Una auditoría ISO produce hallazgos que no cuelgan de ninguna
 * medida —«el programa de auditoría interna no está definido», «la dirección no
 * ha revisado el SGSI»—, y con el punto obligatorio acabarían colgados de un
 * requisito arbitrario, que es el vicio que `OrigenTarea::Propia` existe para
 * evitar.
 *
 * Cuando sí hay punto, la auditoría se toma **de él** y no del formulario: así un
 * punto de otra auditoría no puede colar un hallazgo donde no toca.
 */
final readonly class RegistrarHallazgo
{
    /**
     * @throws AuditoriaCerrada
     */
    public function registrar(
        Auditoria $auditoria,
        TipoHallazgo $tipo,
        string $descripcion,
        ?AuditoriaPunto $punto = null,
    ): Hallazgo {
        if (! $auditoria->admiteCambios()) {
            throw AuditoriaCerrada::paraHallazgo($auditoria);
        }

        return Hallazgo::query()->create([
            'auditoria_id' => $auditoria->id,
            'auditoria_punto_id' => $punto?->id,
            'tipo' => $tipo->value,
            'descripcion' => $descripcion,
        ]);
    }

    /**
     * @throws AuditoriaCerrada
     */
    public function retirar(Hallazgo $hallazgo): void
    {
        $auditoria = $hallazgo->auditoria;

        if ($auditoria !== null && ! $auditoria->admiteCambios()) {
            throw AuditoriaCerrada::paraHallazgo($auditoria);
        }

        $hallazgo->delete();
    }
}
