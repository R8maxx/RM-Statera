<?php

declare(strict_types=1);

namespace App\Domain\Plataforma;

use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * Crea o cambia un plan (punto 43), y lo deja en la traza de la plataforma.
 *
 * **Un plan no se borra**: lo tienen o lo tuvieron clientes, y el histórico de
 * sus suscripciones apunta a él. Se retira con `activo`, que lo saca de los
 * desplegables sin tocar a quien ya lo tiene.
 */
final class GuardarPlan
{
    public function __construct(private readonly TrazaPlataforma $traza) {}

    /**
     * @param  array{codigo: string, nombre: string, descripcion?: ?string, limite_cuentas?: ?int, limite_sistemas?: ?int, dias_gracia: int, activo: bool}  $datos
     */
    public function __invoke(?Plan $plan, array $datos): Plan
    {
        return DB::transaction(function () use ($plan, $datos): Plan {
            $plan ??= new Plan;
            $plan->fill($datos)->save();

            $this->traza->registrar(AccionPlataforma::PlanGuardado, null, [
                'plan' => $plan->codigo,
                'cambios' => $plan->wasRecentlyCreated ? 'alta' : array_keys($plan->getChanges()),
            ]);

            return $plan;
        });
    }
}
