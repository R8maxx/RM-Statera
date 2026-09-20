<?php

declare(strict_types=1);

namespace App\Domain\NoConformidad\Excepciones;

use App\Domain\Auditoria\Enums\TipoHallazgo;
use DomainException;

/**
 * Un hallazgo que no se trata abriendo una no conformidad.
 *
 * Hoy hay un solo caso, y llegó con la cláusula 10.1: una **oportunidad de
 * mejora** no incumple nada, así que tratarla como no conformidad la etiquetaría
 * de incumplimiento y la contaría como tal en el panel, en el indicador del
 * § 4.14 y en la entrada de la 9.3 que la revisión por la dirección lee.
 *
 * La regla vive en el dominio y no sólo en el `FormRequest` porque vale también
 * para un importador — mismo criterio que el motivo de `descartada` en tareas.
 */
final class HallazgoNoTratable extends DomainException
{
    public function __construct(public readonly TipoHallazgo $tipo)
    {
        parent::__construct(sprintf(
            'Un hallazgo de tipo «%s» no se trata como no conformidad: no incumple ningún requisito. '
            .'Su registro es el de oportunidades de mejora (cláusula 10.1).',
            $tipo->etiqueta(),
        ));
    }
}
