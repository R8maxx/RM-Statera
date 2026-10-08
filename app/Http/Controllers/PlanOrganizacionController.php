<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\ContratarPlan;
use App\Domain\Plataforma\Enums\EstadoSuscripcion;
use App\Domain\Plataforma\Enums\PeriodoFacturacion;
use App\Domain\Plataforma\Excepciones\ContratacionNoPermitida;
use App\Domain\Plataforma\LimitesDelPlan;
use App\Domain\Plataforma\Models\Plan;
use App\Domain\Plataforma\PresupuestarCambioPlan;
use App\Domain\Plataforma\PresupuestoCambioPlan;
use App\Http\Requests\ContratarPlanRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La organización elige y cambia de plan (punto 51).
 *
 * Sin parámetro de ruta, por lo mismo que `OrganizacionController`: la
 * organización sale del contexto. Cada plan llega con su presupuesto **ya
 * calculado en los dos periodos**, para que la pantalla no haga cuentas: el
 * importe que se enseña es el mismo que `ContratarPlan` guardará, porque lo
 * calcula la misma clase.
 */
class PlanOrganizacionController extends Controller
{
    public function __construct(private readonly ContextoOrganizacion $contexto) {}

    public function index(PresupuestarCambioPlan $presupuestar, LimitesDelPlan $limites): Response
    {
        $organizacion = $this->actual();
        $uso = $limites->uso($organizacion);
        $estado = EstadoSuscripcion::de($organizacion);

        $planes = Plan::query()->where('activo', true)->orderBy('precio_mensual_centimos')->orderBy('nombre')->get();

        return Inertia::render('organizacion/Planes', [
            'actual' => [
                'planId' => $organizacion->plan_id,
                'plan' => $organizacion->plan?->nombre,
                // Para decir «subir» o «bajar»: el precio al mes del plan que se tiene.
                'precioMensualCentimos' => $organizacion->plan?->precio_mensual_centimos,
                'periodo' => $organizacion->suscripcion_periodo?->value,
                'venceEn' => $organizacion->suscripcion_vence_en?->toIso8601String(),
                'estado' => ['valor' => $estado->value, 'etiqueta' => $estado->etiqueta(), 'tono' => $estado->tono(), 'icono' => $estado->icono()],
            ],
            'uso' => $uso,
            'planes' => $planes
                ->filter(static fn (Plan $plan): bool => $plan->contratable)
                ->map(fn (Plan $plan): array => [
                    'id' => $plan->id,
                    'nombre' => $plan->nombre,
                    'descripcion' => $plan->descripcion,
                    'limiteCuentas' => $plan->limite_cuentas,
                    'limiteSistemas' => $plan->limite_sistemas,
                    'diasGracia' => $plan->dias_gracia,
                    'descuentoAnual' => $plan->descuento_anual,
                    'precioMensualCentimos' => (int) $plan->precio_mensual_centimos,
                    'presupuestos' => [
                        PeriodoFacturacion::Mensual->value => self::presupuesto($presupuestar($organizacion, $plan, PeriodoFacturacion::Mensual, $uso)),
                        PeriodoFacturacion::Anual->value => self::presupuesto($presupuestar($organizacion, $plan, PeriodoFacturacion::Anual, $uso)),
                    ],
                ])
                ->values()
                ->all(),
            // Los que asigna sólo la plataforma, como «Ilimitado»: se ven, no se contratan.
            'reservados' => $planes
                ->reject(static fn (Plan $plan): bool => $plan->contratable)
                ->map(static fn (Plan $plan): array => [
                    'id' => $plan->id,
                    'nombre' => $plan->nombre,
                    'descripcion' => $plan->descripcion,
                    'limiteCuentas' => $plan->limite_cuentas,
                    'limiteSistemas' => $plan->limite_sistemas,
                    'esElActual' => $plan->id === $organizacion->plan_id,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function store(ContratarPlanRequest $request, ContratarPlan $contratar): RedirectResponse
    {
        try {
            $presupuesto = $contratar($this->actual(), $request->plan(), $request->periodo());
        } catch (ContratacionNoPermitida $error) {
            return back()->withErrors(['plan_id' => $error->getMessage()]);
        }

        Inertia::flash('exito', "Ya tenéis el plan {$presupuesto->plan->nombre}. Los nuevos límites funcionan desde ahora.");

        return to_route('organizacion.edit');
    }

    /**
     * @return array{bloqueo: ?string, motivo: ?string, periodoNuevo: bool, diasRestantes: int, ajusteCentimos: int, importeHoyCentimos: int, saldoAFavorCentimos: int, venceEn: string, siguienteCobroCentimos: int}
     */
    private static function presupuesto(PresupuestoCambioPlan $presupuesto): array
    {
        return [
            'bloqueo' => $presupuesto->bloqueo,
            'motivo' => $presupuesto->permitido() ? null : ContratacionNoPermitida::porPresupuesto($presupuesto)->getMessage(),
            'periodoNuevo' => $presupuesto->periodoNuevo,
            'diasRestantes' => $presupuesto->diasRestantes,
            'ajusteCentimos' => $presupuesto->ajusteCentimos,
            'importeHoyCentimos' => $presupuesto->importeHoyCentimos(),
            'saldoAFavorCentimos' => $presupuesto->saldoAFavorCentimos(),
            'venceEn' => $presupuesto->venceEn->toIso8601String(),
            'siguienteCobroCentimos' => $presupuesto->siguienteCobroCentimos,
        ];
    }

    private function actual(): Organizacion
    {
        return Organizacion::query()->with('plan')->findOrFail($this->contexto->idObligatorio());
    }
}
