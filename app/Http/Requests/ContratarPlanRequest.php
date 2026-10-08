<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\PeriodoFacturacion;
use App\Domain\Plataforma\Excepciones\ContratacionNoPermitida;
use App\Domain\Plataforma\LimitesDelPlan;
use App\Domain\Plataforma\Models\Plan;
use App\Domain\Plataforma\PresupuestarCambioPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * La organización elige plan y periodo (punto 51).
 *
 * Sólo los planes activos y contratables: «Ilimitado» y cualquier otro que la
 * plataforma no marque lo asigna ella. `planes` es dato global, como el
 * catálogo, así que el `exists` no se acota por organización.
 */
class ContratarPlanRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => [
                'required',
                'integer',
                Rule::exists('planes', 'id')->where('activo', true)->where('contratable', true),
            ],
            'periodo' => ['required', Rule::enum(PeriodoFacturacion::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'plan_id.exists' => 'Ese plan no se contrata desde aquí: lo asigna el equipo de Statera.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['plan_id' => 'plan'];
    }

    /**
     * Lo mismo que comprobará `ContratarPlan`, para que el motivo salga junto
     * al plan: que ya sea el vuestro o que lo que usáis no quepa.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validador): void {
                if ($validador->errors()->isNotEmpty()) {
                    return;
                }

                // `input()` y no `validated()`: desde un `after()`, éste vuelve a
                // pasar el validador y con él este mismo gancho.
                $organizacion = Organizacion::query()->with('plan')->findOrFail(app(ContextoOrganizacion::class)->idObligatorio());
                $presupuesto = app(PresupuestarCambioPlan::class)(
                    $organizacion,
                    Plan::query()->findOrFail((int) $this->input('plan_id')),
                    PeriodoFacturacion::from((string) $this->input('periodo')),
                    app(LimitesDelPlan::class)->uso($organizacion),
                );

                if (! $presupuesto->permitido()) {
                    $validador->errors()->add('plan_id', ContratacionNoPermitida::porPresupuesto($presupuesto)->getMessage());
                }
            },
        ];
    }

    public function plan(): Plan
    {
        return Plan::query()->findOrFail((int) $this->validated('plan_id'));
    }

    public function periodo(): PeriodoFacturacion
    {
        return PeriodoFacturacion::from((string) $this->validated('periodo'));
    }
}
