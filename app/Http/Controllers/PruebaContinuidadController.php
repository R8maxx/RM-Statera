<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Continuidad\CancelarPrueba;
use App\Domain\Continuidad\CodigoPrueba;
use App\Domain\Continuidad\DerivarDePrueba;
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
use App\Domain\Continuidad\RegistroContinuidad;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Mejora\CodigoMejora;
use App\Domain\NoConformidad\CodigoNoConformidad;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Http\Requests\CancelarPruebaRequest;
use App\Http\Requests\DerivarMejoraDePruebaRequest;
use App\Http\Requests\DerivarNoConformidadDePruebaRequest;
use App\Http\Requests\DerivarTareaDePruebaRequest;
use App\Http\Requests\GuardarPruebaRequest;
use App\Http\Requests\RegistrarResultadoPruebaRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
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
 *
 * **Las costuras: `derivarTarea()`, `derivarNoConformidad()` y
 * `derivarMejora()`.** Cada una valida con el `FormRequest` del módulo destino
 * —recortado, sin `origen`— y delega en `DerivarDePrueba`, que es quien sabe de
 * tareas, no conformidades y mejoras y quien comprueba que la prueba está
 * `realizada` con resultado distinto de `superada`. El permiso de cada ruta es
 * el de escritura del módulo destino —`tareas.gestionar`, `no_conformidades.
 * gestionar`, `mejoras.gestionar`— y no `continuidad.gestionar`: quien puede
 * planificar una prueba no tiene por qué poder abrir no conformidades.
 */
class PruebaContinuidadController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, PruebaContinuidadRecurso $recurso, RegistroContinuidad $registro): Response
    {
        return Inertia::render('continuidad/pruebas/Index', [
            ...$this->tabla($recurso, $request),
            'alertas' => $registro->alertasDePruebas(),
            'pendientes' => $registro->pendientesDePruebas(),
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
        $prueba->load([
            'plan', 'responsable', 'evidencia', 'servicios', 'transiciones.usuario',
            'tareas.responsable', 'noConformidad',
        ]);

        $puedeDerivar = $prueba->estado === EstadoPrueba::Realizada
            && $prueba->resultado !== ResultadoPrueba::Superada;

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
            /*
             * Lo derivado: el trabajo correctivo (por la pivote) y la no
             * conformidad (por `prueba_continuidad_id`). La mejora no aparece
             * aquí —no lleva clave foránea, como la que sale de un incidente—:
             * el botón la manda a su propio registro con el origen ya puesto.
             */
            'tareasDerivadas' => $prueba->tareas
                ->map(static fn (Tarea $tarea): array => [
                    'id' => $tarea->id,
                    'titulo' => $tarea->titulo,
                    'estado' => $tarea->estado->value,
                    'estadoEtiqueta' => $tarea->estado->etiqueta(),
                    'estadoTono' => $tarea->estado->tono(),
                    'responsable' => $tarea->responsable?->name,
                ])
                ->values()
                ->all(),
            'noConformidadDerivada' => $prueba->noConformidad === null ? null : [
                'id' => $prueba->noConformidad->id,
                'codigo' => $prueba->noConformidad->codigo,
                'estado' => $prueba->noConformidad->estado->value,
                'estadoEtiqueta' => $prueba->noConformidad->estado->etiqueta(),
                'estadoTono' => $prueba->noConformidad->estado->tono(),
                'estadoIcono' => $prueba->noConformidad->estado->icono(),
            ],
            /*
             * Sugerencias de código para los dos formularios que lo piden: las
             * tareas no tienen código propio. Propone y no impone, como en el
             * resto del producto — se manda editable en el formulario.
             */
            'sugerenciaCodigoNoConformidad' => app(CodigoNoConformidad::class)->siguiente(),
            'sugerenciaCodigoMejora' => app(CodigoMejora::class)->siguiente(),
            'prioridades' => array_map(
                static fn (PrioridadTarea $prioridad): array => [
                    'valor' => $prioridad->value,
                    'etiqueta' => $prioridad->etiqueta(),
                ],
                PrioridadTarea::cases(),
            ),
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
            'puedeGestionar' => $this->puede(Permiso::ContinuidadGestionar),
            'puedeDerivar' => $puedeDerivar,
            'puedeAbrirTarea' => $puedeDerivar && $this->puede(Permiso::TareasGestionar),
            'puedeTratar' => $puedeDerivar && $this->puede(Permiso::NoConformidadesGestionar),
            'puedeMejorar' => $puedeDerivar && $this->puede(Permiso::MejorasGestionar),
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

    // --- Las costuras: tareas, no conformidades y mejoras -------------------

    public function derivarTarea(
        DerivarTareaDePruebaRequest $request,
        PruebaContinuidad $prueba,
        DerivarDePrueba $derivar,
    ): RedirectResponse {
        try {
            $tarea = $derivar->tarea($prueba, $request->validated(), $request->user());
        } catch (TransicionDePruebaNoPermitida $error) {
            return back()->withErrors(['titulo' => $error->getMessage()]);
        }

        Inertia::flash('exito', "Tarea «{$tarea->titulo}» abierta.");

        return back();
    }

    public function derivarNoConformidad(
        DerivarNoConformidadDePruebaRequest $request,
        PruebaContinuidad $prueba,
        DerivarDePrueba $derivar,
    ): RedirectResponse {
        try {
            $noConformidad = $derivar->noConformidad($prueba, $request->validated(), $request->user());
        } catch (TransicionDePruebaNoPermitida $error) {
            return back()->withErrors(['codigo' => $error->getMessage()]);
        }

        Inertia::flash('exito', "No conformidad {$noConformidad->codigo} registrada.");

        return to_route('no-conformidades.show', $noConformidad);
    }

    public function derivarMejora(
        DerivarMejoraDePruebaRequest $request,
        PruebaContinuidad $prueba,
        DerivarDePrueba $derivar,
    ): RedirectResponse {
        try {
            $mejora = $derivar->mejora($prueba, $request->validated(), $request->user());
        } catch (TransicionDePruebaNoPermitida $error) {
            return back()->withErrors(['codigo' => $error->getMessage()]);
        }

        Inertia::flash('exito', "Oportunidad de mejora {$mejora->codigo} registrada.");

        return to_route('mejoras.show', $mejora);
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
