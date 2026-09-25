<?php

declare(strict_types=1);

namespace App\Domain\Vulnerabilidad;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Vulnerabilidad\Enums\Severidad;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use Illuminate\Support\Carbon;

/**
 * Hasta cuándo hay para remediar una vulnerabilidad.
 *
 * **Se deriva**: fecha de detección más los días que la organización fija para
 * su severidad. Se guarda en `vulnerabilidades.fecha_limite` sólo porque el
 * calendario la consulta por rango, igual que la próxima evaluación de un
 * proveedor. Hay que llamar aquí cuando cambia la severidad, la fecha de
 * detección o la política.
 *
 * Una informativa no tiene plazo.
 */
final class PlazoRemediacion
{
    public function de(Organizacion $organizacion, Severidad $severidad, Carbon $deteccion): ?Carbon
    {
        $dias = $organizacion->diasRemediacion($severidad);

        return $dias === null ? null : $deteccion->copy()->addDays($dias);
    }

    public function recalcular(Vulnerabilidad $vulnerabilidad): void
    {
        $organizacion = Organizacion::query()->findOrFail($vulnerabilidad->organizacion_id);
        $limite = $this->de($organizacion, $vulnerabilidad->severidad, $vulnerabilidad->fecha_deteccion);

        if ($limite?->toDateString() !== $vulnerabilidad->fecha_limite?->toDateString()) {
            $vulnerabilidad->forceFill(['fecha_limite' => $limite])->save();
        }
    }

    /** Tras cambiar la política: todas las de la organización del contexto. */
    public function todas(): void
    {
        foreach (Vulnerabilidad::query()->get() as $vulnerabilidad) {
            $this->recalcular($vulnerabilidad);
        }
    }
}
