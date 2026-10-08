<?php

declare(strict_types=1);

namespace App\Domain\Plataforma;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\OrigenCambioSuscripcion;
use App\Domain\Plataforma\Models\Plan;
use App\Domain\Plataforma\Models\TransicionSuscripcion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cambia el plan o las fechas de la suscripción de un cliente (punto 43).
 *
 * Desde la plataforma; la organización contrata por su cuenta con
 * `ContratarPlan` (punto 51). Queda en tres sitios, cada uno para quien lo
 * pregunta:
 *
 * - en `transiciones_suscripcion`, el histórico del invariante 7: desde cuándo
 *   tiene este plan y quién se lo puso;
 * - en la traza de la plataforma, con el resto de lo que hizo el administrador;
 * - en la traza del tenant, porque el cliente tiene que poder ver en la suya
 *   cuándo le cambiaron el plan. Ésa la escribe sola `Organizacion::booted()`,
 *   y por eso el cambio se guarda **dentro de su contexto**: una escritura que
 *   cruce la frontera la tumbaría RLS al intentar dejar el evento.
 *
 * Cambiar nada no deja rastro.
 */
final class CambiarSuscripcion
{
    public function __construct(
        private readonly ContextoOrganizacion $contexto,
        private readonly TrazaPlataforma $traza,
    ) {}

    public function __invoke(Organizacion $organizacion, ?Plan $plan, ?Carbon $venceEn, ?string $motivo = null): void
    {
        $planAnterior = $organizacion->plan_id;
        $venceAnterior = $organizacion->suscripcion_vence_en;
        $periodoAnterior = $organizacion->suscripcion_periodo;

        // Sin plan no hay vencimiento: la fecha que llegue no se guarda.
        $venceEn = $plan === null ? null : $venceEn;

        $mismoVencimiento = $venceAnterior === null || $venceEn === null
            ? $venceAnterior === $venceEn
            : $venceAnterior->equalTo($venceEn);

        if ($planAnterior === $plan?->id && $mismoVencimiento) {
            return;
        }

        DB::transaction(function () use ($organizacion, $plan, $venceEn, $motivo, $planAnterior, $venceAnterior, $periodoAnterior): void {
            $this->contexto->paraOrganizacion($organizacion, function () use ($organizacion, $plan, $venceEn): void {
                $organizacion->forceFill([
                    'plan_id' => $plan?->id,
                    'suscripcion_inicia_en' => $plan === null ? null : ($organizacion->suscripcion_inicia_en ?? Carbon::now()),
                    'suscripcion_vence_en' => $venceEn,
                    // El periodo lo elige la organización al contratar (punto
                    // 51). La plataforma lo conserva, y sin plan no hay.
                    'suscripcion_periodo' => $plan === null ? null : $organizacion->suscripcion_periodo,
                ])->save();
            });

            TransicionSuscripcion::query()->create([
                'organizacion_afectada_id' => $organizacion->id,
                'plan_anterior_id' => $planAnterior,
                'plan_nuevo_id' => $plan?->id,
                'vence_en_anterior' => $venceAnterior,
                'vence_en_nuevo' => $organizacion->suscripcion_vence_en,
                'motivo' => $motivo,
                'periodo_anterior' => $periodoAnterior?->value,
                'periodo_nuevo' => $organizacion->suscripcion_periodo?->value,
                'origen' => OrigenCambioSuscripcion::Plataforma->value,
                'usuario_id' => Auth::id(),
                'created_at' => Carbon::now(),
            ]);

            $this->traza->registrar(AccionPlataforma::SuscripcionCambiada, $organizacion, [
                'plan' => $plan?->codigo,
                'vence_en' => $organizacion->suscripcion_vence_en?->toIso8601String(),
            ]);
        });

        $organizacion->unsetRelation('plan');
    }
}
