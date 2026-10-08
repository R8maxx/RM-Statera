<?php

declare(strict_types=1);

namespace App\Http\Controllers\Plataforma;

use App\Domain\Plataforma\GuardarPlan;
use App\Domain\Plataforma\Models\Plan;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarPlanRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los planes que se venden (punto 43). Pocos y sin paginar: no es una tabla
 * que vaya a crecer, así que no pasa por la capa de recursos.
 */
class PlanController extends Controller
{
    public function index(): Response
    {
        $clientes = DB::table('organizaciones')
            ->whereNotNull('plan_id')
            ->selectRaw('plan_id, count(*) as total')
            ->groupBy('plan_id')
            ->pluck('total', 'plan_id');

        return Inertia::render('plataforma/planes/Index', [
            'planes' => Plan::query()
                ->orderByDesc('activo')
                ->orderBy('nombre')
                ->get()
                ->map(static fn (Plan $plan): array => [
                    ...self::plan($plan),
                    'clientes' => (int) ($clientes[$plan->id] ?? 0),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('plataforma/planes/Formulario', ['plan' => null]);
    }

    public function store(GuardarPlanRequest $request, GuardarPlan $guardar): RedirectResponse
    {
        $plan = $guardar(null, $request->datos());

        Inertia::flash('exito', "Plan {$plan->nombre} creado.");

        return to_route('plataforma.planes.index');
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('plataforma/planes/Formulario', ['plan' => self::plan($plan)]);
    }

    public function update(GuardarPlanRequest $request, Plan $plan, GuardarPlan $guardar): RedirectResponse
    {
        $guardar($plan, $request->datos());

        Inertia::flash('exito', "Plan {$plan->nombre} guardado.");

        return to_route('plataforma.planes.index');
    }

    /**
     * @return array{id: int, codigo: string, nombre: string, descripcion: ?string, limiteCuentas: ?int, limiteSistemas: ?int, diasGracia: int, activo: bool, precioMensualCentimos: ?int, descuentoAnual: int, contratable: bool}
     */
    private static function plan(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'codigo' => $plan->codigo,
            'nombre' => $plan->nombre,
            'descripcion' => $plan->descripcion,
            'limiteCuentas' => $plan->limite_cuentas,
            'limiteSistemas' => $plan->limite_sistemas,
            'diasGracia' => $plan->dias_gracia,
            'activo' => $plan->activo,
            'precioMensualCentimos' => $plan->precio_mensual_centimos,
            'descuentoAnual' => $plan->descuento_anual,
            'contratable' => $plan->contratable,
        ];
    }
}
