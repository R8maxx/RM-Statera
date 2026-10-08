<?php

declare(strict_types=1);

namespace App\Domain\Plataforma;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\PeriodoFacturacion;
use App\Domain\Plataforma\Models\Plan;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Calcula qué supone pasar a un plan desde la suscripción que se tiene
 * (punto 51). Es el prorrateo de cualquier tienda de suscripciones, con dos
 * casos:
 *
 * - **Mismo periodo y suscripción vigente: se conserva la fecha de
 *   renovación.** Se paga hoy la diferencia por los días que quedan, o queda a
 *   favor si se baja. Subir de plan a mitad de año no mueve el vencimiento.
 * - **En cualquier otro caso empieza un periodo hoy**: sin plan, con la
 *   suscripción vencida (en gracia o en sólo lectura), con un plan sin precio o
 *   al pasar de mensual a anual. Se paga el periodo entero, menos lo que
 *   quedara sin consumir del anterior.
 *
 * **Bajar exige que quepa lo que se usa.** La plataforma puede dejar a un
 * cliente por encima de su límite (`plataforma.md`), porque lo hace alguien
 * que habla con él. Que la organización se lo haga a sí misma sin darse cuenta
 * es otra cosa: no podría invitar a nadie ni dar de alta un sistema hasta
 * quitar lo que sobra. Se le dice qué sobra, y lo quita antes.
 *
 * Los días se cuentan enteros y hacia arriba: el día de hoy, empezado, cuenta
 * como del periodo que se deja.
 */
final class PresupuestarCambioPlan
{
    /**
     * @param  array{cuentas: int, sistemas: int}  $uso  lo ocupado hoy, de `LimitesDelPlan`
     */
    public function __invoke(
        Organizacion $organizacion,
        Plan $plan,
        PeriodoFacturacion $periodo,
        array $uso,
        ?Carbon $ahora = null,
    ): PresupuestoCambioPlan {
        $ahora ??= Carbon::now();
        $precioNuevo = $plan->precioDelPeriodo($periodo)
            ?? throw new InvalidArgumentException("El plan {$plan->codigo} no tiene precio y no se puede presupuestar.");

        $actual = $organizacion->plan;
        $periodoActual = $organizacion->suscripcion_periodo;
        $vence = $organizacion->suscripcion_vence_en;
        $vigente = $actual !== null && $vence !== null && $ahora->lt($vence);

        $sobranCuentas = $plan->limite_cuentas === null ? 0 : max(0, $uso['cuentas'] - $plan->limite_cuentas);
        $sobranSistemas = $plan->limite_sistemas === null ? 0 : max(0, $uso['sistemas'] - $plan->limite_sistemas);

        $bloqueo = match (true) {
            $vigente && $actual->id === $plan->id && $periodoActual === $periodo => PresupuestoCambioPlan::ES_EL_ACTUAL,
            $sobranCuentas > 0 || $sobranSistemas > 0 => PresupuestoCambioPlan::NO_CABE,
            default => null,
        };

        // Lo que queda sin consumir del periodo en curso, si se pagó.
        $precioActual = $vigente && $periodoActual !== null ? $actual->precioDelPeriodo($periodoActual) : null;
        $diasPeriodo = 1;
        $diasRestantes = 0;

        if ($precioActual !== null && $periodoActual !== null) {
            $empezo = $vence->copy()->subMonthsNoOverflow($periodoActual->meses());
            $diasPeriodo = max(1, (int) ceil(abs($empezo->diffInDays($vence))));
            $diasRestantes = min($diasPeriodo, (int) ceil(abs($ahora->diffInDays($vence))));
        }

        $sigue = $precioActual !== null && $periodoActual === $periodo;

        if ($sigue) {
            $ajuste = (int) round(($precioNuevo - $precioActual) * $diasRestantes / $diasPeriodo);
            $iniciaEn = $organizacion->suscripcion_inicia_en ?? $ahora->copy();
            $venceEn = $vence->copy();
        } else {
            $credito = $precioActual === null ? 0 : (int) round($precioActual * $diasRestantes / $diasPeriodo);
            $ajuste = $precioNuevo - $credito;
            $iniciaEn = $ahora->copy();
            $venceEn = $ahora->copy()->addMonthsNoOverflow($periodo->meses())->endOfDay();
        }

        return new PresupuestoCambioPlan(
            plan: $plan,
            periodo: $periodo,
            bloqueo: $bloqueo,
            sobranCuentas: $sobranCuentas,
            sobranSistemas: $sobranSistemas,
            periodoNuevo: ! $sigue,
            diasRestantes: $diasRestantes,
            ajusteCentimos: $ajuste,
            iniciaEn: $iniciaEn,
            venceEn: $venceEn,
            siguienteCobroCentimos: $precioNuevo,
        );
    }
}
