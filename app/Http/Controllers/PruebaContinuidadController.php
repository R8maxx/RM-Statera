<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Continuidad\CancelarPrueba;
use App\Domain\Continuidad\CodigoPrueba;
use App\Domain\Continuidad\EditarPrueba;
use App\Domain\Continuidad\Enums\EstadoPrueba;
use App\Domain\Continuidad\Enums\ResultadoPrueba;
use App\Domain\Continuidad\Enums\TipoPrueba;
use App\Domain\Continuidad\Excepciones\ServicioNoValido;
use App\Domain\Continuidad\Excepciones\TransicionDePruebaNoPermitida;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Continuidad\Models\PruebaContinuidadTransicion;
use App\Domain\Continuidad\PlanificarPrueba;
use App\Domain\Continuidad\RegistrarResultadoPrueba;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Requests\CancelarPruebaRequest;
use App\Http\Requests\GuardarPruebaRequest;
use App\Http\Requests\RegistrarResultadoPruebaRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\Panel\Indicador;
use App\Http\Resources\PruebaContinuidadRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las pruebas de un plan de continuidad: § 4.11 y `op.cont.3`.
 *
 * **Sobre `IncidenteController`**, con el mismo reparto de rutas: lectura tras
 * `continuidad.ver`, escritura tras `continuidad.gestionar` y segundo factor.
 * Sin `continuidad.aprobar`: aquí no hay nada que aceptar como riesgo, hay algo
 * que comprobar.
 *
 * **`update()` sólo mientras la prueba está `planificada`.** No hay un
 * `CambiarEstadoPrueba` genérico —ver la cabecera de `EstadoPrueba`—, así que
 * una prueba `realizada` o `cancelada` no se reescribe: se planifica la
 * siguiente. La interfaz oculta «Editar» fuera de ese estado; el guardián de
 * verdad está aquí.
 *
 * **Sin `destroy()`.** Una prueba cancelada o fallida sigue siendo la prueba
 * de que se probó: borrarla perdería justamente lo que `op.cont.3` exige
 * conservar, igual que un BIA no se borra.
 */
class PruebaContinuidadController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, PruebaContinuidadRecurso $recurso): Response
    {
        return Inertia::render('continuidad/pruebas/Index', [
            ...$this->tabla($recurso, $request),
            'alertas' => $this->alertas(),
            'pendientes' => $this->pendientes(),
            'total' => PruebaContinuidad::query()->count(),
        ]);
    }

    public function create(CodigoPrueba $codigos): Response
    {
        return Inertia::render('continuidad/pruebas/Formulario', [
            'prueba' => null,
            'serviciosVinculados' => [],
            'sugerencia' => ['codigo' => $codigos->siguiente()],
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarPruebaRequest $request, PlanificarPrueba $planificar): RedirectResponse
    {
        $datos = $request->validated();
        $servicios = array_map('intval', $datos['servicios']);
        unset($datos['servicios']);

        try {
            $prueba = $planificar($datos, $servicios, $request->user());
        } catch (ServicioNoValido $error) {
            return back()->withErrors(['servicios' => $error->getMessage()]);
        }

        Inertia::flash('exito', "Prueba {$prueba->codigo} planificada.");

        return to_route('continuidad.pruebas.show', $prueba);
    }

    public function show(PruebaContinuidad $prueba): Response
    {
        $prueba->load(['plan', 'responsable', 'evidencia', 'servicios', 'transiciones.usuario']);

        // Los BIA de los servicios de esta prueba, en una consulta y no una
        // por servicio: ver el docblock de `PruebaContinuidad::excedeRto()`.
        $biasPorActivo = BiaServicio::query()
            ->whereIn('activo_id', $prueba->servicios->pluck('id'))
            ->get()
            ->keyBy('activo_id');

        return Inertia::render('continuidad/pruebas/Ficha', [
            'prueba' => $this->serializar($prueba),
            'servicios' => $prueba->servicios
                ->map(function (Activo $servicio) use ($prueba, $biasPorActivo): array {
                    $bia = $biasPorActivo->get($servicio->id);
                    $pivot = $servicio->getAttribute('pivot');

                    return [
                        'id' => $servicio->id,
                        'codigo' => $servicio->codigo,
                        'nombre' => $servicio->nombre,
                        'rtoObjetivo' => $bia?->rto_horas,
                        'rtoAlcanzado' => $pivot?->getAttribute('rto_alcanzado_horas'),
                        'rpoObjetivo' => $bia?->rpo_horas,
                        'rpoAlcanzado' => $pivot?->getAttribute('rpo_alcanzado_horas'),
                        'excedeRto' => $prueba->excedeRto($servicio, $bia),
                    ];
                })
                ->values()
                ->all(),
            'historial' => $prueba->transiciones
                ->map(static fn (PruebaContinuidadTransicion $transicion): array => [
                    'id' => $transicion->id,
                    'anterior' => $transicion->estado_anterior?->etiqueta(),
                    'nuevo' => $transicion->estado_nuevo->etiqueta(),
                    'tono' => $transicion->estado_nuevo->tono(),
                    'icono' => $transicion->estado_nuevo->icono(),
                    'usuario' => $transicion->usuario?->name,
                    'fecha' => $transicion->created_at->toIso8601String(),
                    'nota' => $transicion->nota,
                ])
                ->values()
                ->all(),
            'evidencias' => $this->evidenciasDisponibles(),
            'resultados' => array_map(
                static fn (ResultadoPrueba $resultado): array => [
                    'valor' => $resultado->value,
                    'etiqueta' => $resultado->etiqueta(),
                ],
                ResultadoPrueba::cases(),
            ),
            'puedeGestionar' => $this->puede(Permiso::ContinuidadGestionar),
        ]);
    }

    public function edit(PruebaContinuidad $prueba): Response|RedirectResponse
    {
        if ($prueba->estado !== EstadoPrueba::Planificada) {
            Inertia::flash('error', 'Sólo se edita una prueba mientras sigue planificada: la realizada o cancelada no se reescribe.');

            return to_route('continuidad.pruebas.show', $prueba);
        }

        $prueba->load('servicios');

        return Inertia::render('continuidad/pruebas/Formulario', [
            'prueba' => $this->serializar($prueba),
            'serviciosVinculados' => $prueba->servicios->pluck('id')->all(),
            'sugerencia' => null,
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarPruebaRequest $request, PruebaContinuidad $prueba, EditarPrueba $editar): RedirectResponse
    {
        if ($prueba->estado !== EstadoPrueba::Planificada) {
            Inertia::flash('error', 'Sólo se edita una prueba mientras sigue planificada: la realizada o cancelada no se reescribe.');

            return to_route('continuidad.pruebas.show', $prueba);
        }

        $datos = $request->validated();
        $servicios = array_map('intval', $datos['servicios']);
        unset($datos['servicios']);

        try {
            $editar($prueba, $datos, $servicios);
        } catch (ServicioNoValido $error) {
            return back()->withErrors(['servicios' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Prueba actualizada.');

        return to_route('continuidad.pruebas.show', $prueba);
    }

    public function resultado(
        RegistrarResultadoPruebaRequest $request,
        PruebaContinuidad $prueba,
        RegistrarResultadoPrueba $registrar,
    ): RedirectResponse {
        try {
            $registrar($prueba, $request->validated(), $request->user());
        } catch (TransicionDePruebaNoPermitida $error) {
            return back()->withErrors(['resultado' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Resultado registrado.');

        return to_route('continuidad.pruebas.show', $prueba);
    }

    public function cancelar(
        CancelarPruebaRequest $request,
        PruebaContinuidad $prueba,
        CancelarPrueba $cancelar,
    ): RedirectResponse {
        try {
            $cancelar($prueba, $request->string('motivo')->value(), $request->user());
        } catch (TransicionDePruebaNoPermitida $error) {
            return back()->withErrors(['motivo' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Prueba cancelada.');

        return to_route('continuidad.pruebas.show', $prueba);
    }

    /**
     * Lo que va mal de verdad, y lo que está a medias.
     *
     * @return list<Indicador>
     */
    private function alertas(): array
    {
        return [
            new Indicador(
                clave: 'vencidas',
                etiqueta: 'Vencidas',
                valor: PruebaContinuidad::query()->vencidas()->count(),
                tono: 'caducada',
                filtro: 'filter[vencidas]=1',
                base: '/continuidad/pruebas',
                ayuda: 'Planificadas cuya fecha prevista ya ha pasado sin registrar resultado.',
            ),
        ];
    }

    /**
     * @return list<Indicador>
     */
    private function pendientes(): array
    {
        return [
            new Indicador(
                clave: 'planificadas',
                etiqueta: 'Planificadas',
                valor: PruebaContinuidad::query()->where('estado', EstadoPrueba::Planificada->value)->count(),
                tono: 'planificado',
                filtro: 'filter[estado]=planificada',
                base: '/continuidad/pruebas',
                ayuda: 'Todavía sin resultado registrado.',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(PruebaContinuidad $prueba): array
    {
        return [
            'id' => $prueba->id,
            'codigo' => $prueba->codigo,
            'titulo' => $prueba->titulo,
            'documento_id' => $prueba->documento_id,
            'plan' => $prueba->plan === null ? null : [
                'id' => $prueba->plan->id,
                'codigo' => $prueba->plan->codigo,
                'titulo' => $prueba->plan->titulo,
            ],
            'tipo' => $prueba->tipo->value,
            'tipoEtiqueta' => $prueba->tipo->etiqueta(),
            'tipoTono' => $prueba->tipo->tono(),
            'tipoIcono' => $prueba->tipo->icono(),
            'estado' => $prueba->estado->value,
            'estadoEtiqueta' => $prueba->estado->etiqueta(),
            'estadoTono' => $prueba->estado->tono(),
            'estadoIcono' => $prueba->estado->icono(),
            'fecha_prevista' => $prueba->fecha_prevista->format('Y-m-d'),
            'fechaPrevistaEtiqueta' => $prueba->fecha_prevista->format('d/m/Y'),
            'fecha_realizacion' => $prueba->fecha_realizacion?->format('Y-m-d'),
            'fechaRealizacionEtiqueta' => $prueba->fecha_realizacion?->format('d/m/Y'),
            'resultado' => $prueba->resultado?->value,
            'resultadoEtiqueta' => $prueba->resultado?->etiqueta(),
            'resultadoTono' => $prueba->resultado?->tono(),
            'resultadoIcono' => $prueba->resultado?->icono(),
            'conclusiones' => $prueba->conclusiones,
            'motivo_cancelacion' => $prueba->motivo_cancelacion,
            'evidencia_id' => $prueba->evidencia_id,
            'evidencia' => $prueba->evidencia?->titulo,
            'responsable_id' => $prueba->responsable_id,
            'responsable' => $prueba->responsable?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'planes' => Documento::query()
                ->where('tipo', TipoDocumento::PlanContinuidad->value)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'titulo'])
                ->map(static fn (Documento $plan): array => [
                    'valor' => (string) $plan->id,
                    'etiqueta' => "{$plan->codigo} · {$plan->titulo}",
                ])
                ->all(),
            'tipos' => array_map(
                static fn (TipoPrueba $tipo): array => [
                    'valor' => $tipo->value,
                    'etiqueta' => $tipo->etiqueta(),
                    'descripcion' => $tipo->descripcion(),
                ],
                TipoPrueba::cases(),
            ),
            'servicios' => Activo::query()
                ->where('tipo', TipoActivo::Servicios->value)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre'])
                ->map(static fn (Activo $activo): array => [
                    'valor' => (string) $activo->id,
                    'etiqueta' => "{$activo->codigo} · {$activo->nombre}",
                ])
                ->all(),
            // Acotado a la organización a mano: `User` no lleva el scope.
            'responsables' => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(static fn (User $usuario): array => [
                    'valor' => (string) $usuario->id,
                    'etiqueta' => $usuario->name,
                ])
                ->all(),
        ];
    }

    /**
     * Las evidencias de la organización, para el desplegable opcional del
     * resultado. Mismo patrón que `FormacionController::opciones()`: la misma
     * evidencia sirve para varios marcos y no se sube una por prueba.
     *
     * @return list<array{valor: string, etiqueta: string}>
     */
    private function evidenciasDisponibles(): array
    {
        return Evidencia::query()
            ->orderByDesc('fecha_obtencion')
            ->limit(100)
            ->get()
            ->map(static fn (Evidencia $evidencia): array => [
                'valor' => (string) $evidencia->id,
                'etiqueta' => $evidencia->titulo,
            ])
            ->all();
    }

    private function puede(Permiso $permiso): bool
    {
        return request()->user()?->can($permiso->value) ?? false;
    }
}
