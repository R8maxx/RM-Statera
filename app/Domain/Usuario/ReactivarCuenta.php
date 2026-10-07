<?php

declare(strict_types=1);

namespace App\Domain\Usuario;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\LimitesDelPlan;
use App\Domain\Traza\Enums\AccionAuditada;
use App\Domain\Traza\RegistroTraza;
use App\Domain\Usuario\Excepciones\OperacionDeCuentaNoPermitida;
use App\Models\User;

/**
 * Devuelve la entrada a una cuenta desactivada.
 *
 * No toca `acceso_hasta`: si la fecha de un auditor ya pasó, la cuenta vuelve
 * como caducada y hay que ampliarla aparte. Reactivar es deshacer una decisión,
 * no alargar un plazo.
 *
 * **Y pasa por el límite de cuentas del plan (punto 43)**: una cuenta
 * desactivada no ocupa asiento, así que reactivarla es añadir uno. Sin esto,
 * desactivar y reactivar sería la forma de tener más cuentas que el plan.
 */
final class ReactivarCuenta
{
    public function __construct(
        private readonly RegistroTraza $traza,
        private readonly LimitesDelPlan $limites,
    ) {}

    public function __invoke(User $cuenta): void
    {
        if ($cuenta->desactivada_en === null) {
            return;
        }

        $organizacion = Organizacion::query()->with('plan')->find($cuenta->organizacion_id);
        $rol = $cuenta->rol();

        if ($organizacion !== null && $rol !== null && ! $this->limites->puedeAnadirCuenta($organizacion, $rol)) {
            throw OperacionDeCuentaNoPermitida::limiteDelPlan();
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
