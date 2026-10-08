<?php

declare(strict_types=1);

namespace App\Domain\Plataforma;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\OrigenCambioSuscripcion;
use App\Domain\Plataforma\Enums\PeriodoFacturacion;
use App\Domain\Plataforma\Excepciones\ContratacionNoPermitida;
use App\Domain\Plataforma\Models\Plan;
use App\Domain\Plataforma\Models\TransicionSuscripcion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * La organización cambia de plan por su cuenta (punto 51).
 *
 * Es la otra puerta de `CambiarSuscripcion`, y deja rastro en los mismos tres
 * sitios: el histórico, la traza de la plataforma —que tiene que enterarse de
 * que un cliente se ha cambiado— y la del tenant, que escribe sola
 * `Organizacion::booted()`. Aquí no hace falta `paraOrganizacion()`: la
 * petición viene del propio cliente, con su contexto ya puesto.
 *
 * **Se cambia al confirmar y no se cobra.** El importe se calcula, se enseña y
 * queda en el histórico con signo; el día que haya pasarela, el cobro va
 * delante del `save()` y, si falla, no se cambia nada.
 *
 * Sólo los planes **activos y contratables**. Un plan sin límites como
 * «Ilimitado» lo asigna la plataforma y no aparece aquí.
 *
 * El presupuesto se vuelve a calcular **dentro de la transacción y con la fila
 * bloqueada**, y lo que se guarda es eso, no lo que enseñó la pantalla: entre
 * una cosa y otra alguien pudo invitar a una cuenta, o dos pestañas pudieron
 * confirmar a la vez.
 */
final class ContratarPlan
{
    public function __construct(
        private readonly PresupuestarCambioPlan $presupuestar,
        private readonly LimitesDelPlan $limites,
        private readonly TrazaPlataforma $traza,
    ) {}

    public function __invoke(Organizacion $organizacion, Plan $plan, PeriodoFacturacion $periodo): PresupuestoCambioPlan
    {
        if (! $plan->activo || ! $plan->contratable) {
            throw ContratacionNoPermitida::noContratable($plan);
        }

        $presupuesto = DB::transaction(function () use ($organizacion, $plan, $periodo): PresupuestoCambioPlan {
            $fila = Organizacion::query()->whereKey($organizacion->id)->lockForUpdate()->firstOrFail();
            $fila->load('plan');

            $presupuesto = ($this->presupuestar)($fila, $plan, $periodo, $this->limites->uso($fila));

            if (! $presupuesto->permitido()) {
                throw ContratacionNoPermitida::porPresupuesto($presupuesto);
            }

            $planAnterior = $fila->plan_id;
            $periodoAnterior = $fila->suscripcion_periodo;
            $venceAnterior = $fila->suscripcion_vence_en;

            $fila->forceFill([
                'plan_id' => $plan->id,
                'suscripcion_periodo' => $periodo,
                'suscripcion_inicia_en' => $presupuesto->iniciaEn,
                'suscripcion_vence_en' => $presupuesto->venceEn,
            ])->save();

            TransicionSuscripcion::query()->create([
                'organizacion_afectada_id' => $fila->id,
                'plan_anterior_id' => $planAnterior,
                'plan_nuevo_id' => $plan->id,
                'vence_en_anterior' => $venceAnterior,
                'vence_en_nuevo' => $presupuesto->venceEn,
                'periodo_anterior' => $periodoAnterior?->value,
                'periodo_nuevo' => $periodo->value,
                'importe_centimos' => $presupuesto->ajusteCentimos,
                'origen' => OrigenCambioSuscripcion::Organizacion->value,
                'usuario_id' => Auth::id(),
                'created_at' => Carbon::now(),
            ]);

            $this->traza->registrar(AccionPlataforma::PlanContratado, $fila, [
                'plan' => $plan->codigo,
                'periodo' => $periodo->value,
                'vence_en' => $presupuesto->venceEn->toIso8601String(),
                'importe_centimos' => $presupuesto->ajusteCentimos,
            ]);

            return $presupuesto;
        });

        $organizacion->refresh();

        return $presupuesto;
    }
}
