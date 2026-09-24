<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\RegistroTraza;
use App\Models\User;

/**
 * Devuelve la entrada a una cuenta desactivada.
 *
 * No toca `acceso_hasta`: si la fecha de un auditor ya pasó, la cuenta vuelve
 * como caducada y hay que ampliarla aparte. Reactivar es deshacer una decisión,
 * no alargar un plazo.
 */
final class ReactivarCuenta
{
    public function __construct(private readonly RegistroTraza $traza) {}

    public function __invoke(User $cuenta): void
    {
        if ($cuenta->desactivada_en === null) {
            return;
        }

        $anterior = [
            'desactivada_en' => $cuenta->desactivada_en->toIso8601String(),
            'motivo_desactivacion' => $cuenta->motivo_desactivacion,
        ];

        $cuenta->forceFill(['desactivada_en' => null, 'motivo_desactivacion' => null])->save();

        $this->traza->evento($cuenta, AccionAuditada::Actualizado, $anterior, [
            'desactivada_en' => null,
            'motivo_desactivacion' => null,
        ]);
    }
}
