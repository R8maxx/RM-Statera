<?php

declare(strict_types=1);

namespace App\Domain\Proveedor;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Proveedor\Models\Proveedor;
use Illuminate\Support\Carbon;

/**
 * Cuándo toca volver a evaluar a un proveedor.
 *
 * **Se deriva** de la última evaluación y de los meses que la organización fija
 * para su criticidad, y se guarda en `proveedores.proxima_evaluacion` sólo
 * porque el calendario la consulta por rango. Por eso hay que llamar aquí cada
 * vez que cambia una de las tres cosas: una evaluación nueva, la criticidad o la
 * política de la organización.
 *
 * Sin evaluación no hay fecha: lo que falta no es reevaluar, es evaluar por
 * primera vez, y eso lo señala el panel. Un proveedor retirado tampoco la
 * tiene: ya no presta nada.
 */
final class RecalcularReevaluacion
{
    public function proximaDe(Proveedor $proveedor): ?Carbon
    {
        if (! $proveedor->estado->seReevalua()) {
            return null;
        }

        $ultima = $proveedor->ultimaEvaluacion()->first();

        if ($ultima === null) {
            return null;
        }

        $organizacion = Organizacion::query()->findOrFail($proveedor->organizacion_id);

        return $ultima->fecha->copy()->addMonthsNoOverflow($organizacion->mesesReevaluacion($proveedor->criticidad()));
    }

    public function recalcular(Proveedor $proveedor): void
    {
        $proxima = $this->proximaDe($proveedor);

        if ($proxima?->toDateString() !== $proveedor->proxima_evaluacion?->toDateString()) {
            $proveedor->forceFill(['proxima_evaluacion' => $proxima])->save();
        }
    }

    /** Tras cambiar la política: todos los de la organización del contexto. */
    public function todos(): void
    {
        foreach (Proveedor::query()->get() as $proveedor) {
            $this->recalcular($proveedor);
        }
    }
}
