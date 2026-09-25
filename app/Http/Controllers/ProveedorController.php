<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Proveedor\CambiarEstadoProveedor;
use App\Domain\Proveedor\CodigoProveedor;
use App\Domain\Proveedor\CriticidadProveedor;
use App\Domain\Proveedor\DerivarTareaDeProveedor;
use App\Domain\Proveedor\Enums\Criticidad;
use App\Domain\Proveedor\Enums\EstadoProveedor;
use App\Domain\Proveedor\Enums\ModeloNube;
use App\Domain\Proveedor\Enums\ResultadoClausula;
use App\Domain\Proveedor\Enums\ResultadoEvaluacion;
use App\Domain\Proveedor\Enums\TipoCertificacion;
use App\Domain\Proveedor\Enums\UbicacionDatos;
use App\Domain\Proveedor\Excepciones\OperacionDeProveedorNoPermitida;
use App\Domain\Proveedor\Models\ClausulaContractual;
use App\Domain\Proveedor\Models\Proveedor;
use App\Domain\Proveedor\Models\ProveedorCertificacion;
use App\Domain\Proveedor\Models\ProveedorEvaluacion;
use App\Domain\Proveedor\Models\ProveedorEvaluacionClausula;
use App\Domain\Proveedor\Models\ProveedorTransicion;
use App\Domain\Proveedor\RecalcularReevaluacion;
use App\Domain\Proveedor\RegistrarEvaluacion;
use App\Domain\Proveedor\RegistroProveedores;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Usuario\CuentasAsignables;
use App\Http\Requests\DerivarTareaDeProveedorRequest;
use App\Http\Requests\GuardarCertificacionProveedorRequest;
use App\Http\Requests\GuardarProveedorRequest;
use App\Http\Requests\RegistrarEvaluacionProveedorRequest;
use App\Http\Requests\RetirarProveedorRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\ProveedorRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Proveedores y terceros: § 4.9, A.5.19 a A.5.23, `op.ext` y `op.nub`.
 *
 * Valida, delega y devuelve. Lo que decide —el mínimo de criticidad, el estado
 * que deja una evaluación, cuándo toca la siguiente— vive en
 * `app/Domain/Proveedor/` y llega aquí como `OperacionDeProveedorNoPermitida`.
 */
class ProveedorController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, ProveedorRecurso $recurso, RegistroProveedores $registro): Response
    {
        return Inertia::render('proveedores/Index', [
            ...$this->tabla($recurso, $request),
            'alertas' => $registro->alertas(),
            'pendientes' => $registro->pendientes(),
            'total' => $registro->total(),
        ]);
    }

    public function create(CodigoProveedor $codigo): Response
    {
        return Inertia::render('proveedores/Formulario', [
            'proveedor' => null,
            'sugerencia' => ['codigo' => $codigo->siguiente()],
            'criticidadDerivada' => null,
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarProveedorRequest $request, CriticidadProveedor $criticidad): RedirectResponse
    {
        $datos = $request->datos();

        try {
            $criticidad->validar(
                null,
                isset($datos['criticidad_declarada']) ? Criticidad::from((string) $datos['criticidad_declarada']) : null,
                $datos['justificacion_criticidad'] ?? null,
            );
        } catch (OperacionDeProveedorNoPermitida $error) {
            return back()->withErrors(['criticidad_declarada' => $error->getMessage()])->withInput();
        }

        $proveedor = DB::transaction(function () use ($datos, $request): Proveedor {
            $proveedor = Proveedor::query()->create($datos);

            ProveedorTransicion::query()->create([
                'proveedor_id' => $proveedor->id,
                'estado_anterior' => null,
                'estado_nuevo' => EstadoProveedor::EnEvaluacion,
                'usuario_id' => $request->user()?->id,
            ]);

            return $proveedor;
        });

        Inertia::flash('exito', "«{$proveedor->nombre}» está en el registro de proveedores. Queda en evaluación hasta que se compruebe su contrato.");

        return to_route('proveedores.show', $proveedor);
    }

    public function show(Request $request, Proveedor $proveedor): Response
    {
        $proveedor->load(['responsable:id,name', 'certificaciones.evidencia:id,titulo', 'tareas', 'activos']);

        return Inertia::render('proveedores/Ficha', [
            'proveedor' => $this->serializar($proveedor),
            'activos' => $proveedor->activos
                ->sortBy('codigo')
                ->map(fn (Activo $activo): array => [
                    'id' => $activo->id,
                    'codigo' => $activo->codigo,
                    'nombre' => $activo->nombre,
                    'nivel' => $activo->valoracion()->nivelMaximo()->etiqueta(),
                ])->values()->all(),
            'certificaciones' => $proveedor->certificaciones
                ->sortByDesc('caduca_en')
                ->map(fn (ProveedorCertificacion $certificacion): array => [
                    'id' => $certificacion->id,
                    'tipo' => $certificacion->tipo->value,
                    'etiqueta' => $certificacion->etiqueta(),
                    'entidadEmisora' => $certificacion->entidad_emisora,
                    'emitidaEn' => $certificacion->emitida_en?->toDateString(),
                    'caducaEn' => $certificacion->caduca_en?->toDateString(),
                    'caducada' => $certificacion->haCaducado(),
                    'evidencia' => $certificacion->evidencia === null ? null : [
                        'id' => $certificacion->evidencia->id,
                        'titulo' => $certificacion->evidencia->titulo,
                    ],
                ])->values()->all(),
            'evaluaciones' => $this->evaluaciones($proveedor),
            'historial' => $proveedor->transiciones()
                ->with('usuario:id,name')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (ProveedorTransicion $transicion): array => [
                    'id' => $transicion->id,
                    'anterior' => $transicion->estado_anterior?->etiqueta(),
                    'nuevo' => $transicion->estado_nuevo->etiqueta(),
                    'tono' => $transicion->estado_nuevo->tono(),
                    'icono' => $transicion->estado_nuevo->icono(),
                    'usuario' => $transicion->usuario?->name,
                    'nota' => $transicion->nota,
                    'fecha' => $transicion->created_at->toIso8601String(),
                ])->values()->all(),
            'tareas' => $proveedor->tareas
                ->map(fn (Tarea $tarea): array => [
                    'id' => $tarea->id,
                    'titulo' => $tarea->titulo,
                    'estado' => $tarea->estado->etiqueta(),
                    'tono' => $tarea->estado->tono(),
                    'icono' => $tarea->estado->icono(),
                ])->values()->all(),
            'clausulas' => $this->clausulasVigentes(),
            'tiposCertificacion' => array_map(
                static fn (TipoCertificacion $tipo): array => ['valor' => $tipo->value, 'etiqueta' => $tipo->etiqueta()],
                TipoCertificacion::cases(),
            ),
            'evidencias' => Evidencia::query()
                ->orderBy('titulo')
                ->get(['id', 'titulo'])
                ->map(fn (Evidencia $evidencia): array => ['valor' => (string) $evidencia->id, 'etiqueta' => $evidencia->titulo])
                ->values()->all(),
            'responsables' => app(CuentasAsignables::class)->opciones(Permiso::TareasGestionar),
            'prioridades' => array_map(
                static fn (PrioridadTarea $prioridad): array => ['valor' => $prioridad->value, 'etiqueta' => $prioridad->etiqueta()],
                PrioridadTarea::cases(),
            ),
            'puedeGestionar' => $request->user()?->can(Permiso::ProveedoresGestionar->value) ?? false,
            'puedeEvaluar' => $request->user()?->can(Permiso::ProveedoresEvaluar->value) ?? false,
            'puedeAbrirTarea' => $request->user()?->can(Permiso::TareasGestionar->value) ?? false,
        ]);
    }

    public function edit(Proveedor $proveedor): Response
    {
        return Inertia::render('proveedores/Formulario', [
            'proveedor' => $this->serializar($proveedor),
            'sugerencia' => null,
            'criticidadDerivada' => $proveedor->criticidad_derivada === null ? null : [
                'valor' => $proveedor->criticidad_derivada->value,
                'etiqueta' => $proveedor->criticidad_derivada->etiqueta(),
            ],
            ...$this->opciones($proveedor->responsable_id),
        ]);
    }

    public function update(
        GuardarProveedorRequest $request,
        Proveedor $proveedor,
        CriticidadProveedor $criticidad,
        RecalcularReevaluacion $reevaluacion,
    ): RedirectResponse {
        $datos = $request->datos();

        try {
            $criticidad->validar(
                $proveedor->criticidad_derivada,
                isset($datos['criticidad_declarada']) ? Criticidad::from((string) $datos['criticidad_declarada']) : null,
                $datos['justificacion_criticidad'] ?? null,
            );
        } catch (OperacionDeProveedorNoPermitida $error) {
            return back()->withErrors(['criticidad_declarada' => $error->getMessage()])->withInput();
        }

        DB::transaction(function () use ($proveedor, $datos, $reevaluacion): void {
            $proveedor->update($datos);
            // La declarada puede haber cambiado la criticidad, y con ella el plazo.
            $reevaluacion->recalcular($proveedor);
        });

        Inertia::flash('exito', 'Proveedor actualizado.');

        return to_route('proveedores.show', $proveedor);
    }

    /**
     * La pantalla de evaluar: todas las cláusulas vigentes, sin respuesta
     * marcada. **Sin copiar la evaluación anterior**, a propósito: lo que se
     * evalúa es el contrato de hoy, y partir de lo que se contestó hace un año
     * es la forma más rápida de no volver a leerlo.
     */
    public function formularioEvaluacion(Proveedor $proveedor): Response|RedirectResponse
    {
        if (! $proveedor->estado->seReevalua()) {
            Inertia::flash('error', OperacionDeProveedorNoPermitida::retirado()->getMessage());

            return to_route('proveedores.show', $proveedor);
        }

        return Inertia::render('proveedores/Evaluar', [
            'proveedor' => [
                'id' => $proveedor->id,
                'codigo' => $proveedor->codigo,
                'nombre' => $proveedor->nombre,
                'criticidad' => $proveedor->criticidad()->etiqueta(),
                'es_nube' => $proveedor->es_nube,
                'es_subencargado_rgpd' => $proveedor->es_subencargado_rgpd,
            ],
            'clausulas' => $this->clausulasVigentes(),
            'resultados' => array_map(
                static fn (ResultadoEvaluacion $resultado): array => ['valor' => $resultado->value, 'etiqueta' => $resultado->etiqueta()],
                ResultadoEvaluacion::cases(),
            ),
            'respuestas' => array_map(
                static fn (ResultadoClausula $resultado): array => ['valor' => $resultado->value, 'etiqueta' => $resultado->etiqueta()],
                ResultadoClausula::cases(),
            ),
            'hoy' => now()->toDateString(),
        ]);
    }

    public function evaluar(RegistrarEvaluacionProveedorRequest $request, Proveedor $proveedor, RegistrarEvaluacion $registrar): RedirectResponse
    {
        /** @var User $quien */
        $quien = $request->user();

        try {
            $evaluacion = $registrar(
                $proveedor,
                $quien,
                $request->fecha(),
                $request->resultado(),
                $request->validated('conclusiones'),
                $request->clausulas(),
            );
        } catch (OperacionDeProveedorNoPermitida $error) {
            return back()->withErrors(['resultado' => $error->getMessage()]);
        }

        $proveedor->refresh();

        Inertia::flash('exito', sprintf(
            'Evaluación registrada: %s. %s',
            mb_strtolower($evaluacion->resultado->etiqueta()),
            $proveedor->proxima_evaluacion === null
                ? ''
                : 'La siguiente toca el '.$proveedor->proxima_evaluacion->format('d/m/Y').'.',
        ));

        return to_route('proveedores.show', $proveedor);
    }

    public function retirar(RetirarProveedorRequest $request, Proveedor $proveedor, CambiarEstadoProveedor $estado): RedirectResponse
    {
        /** @var User $quien */
        $quien = $request->user();

        try {
            $estado->retirar($proveedor, $quien, (string) $request->validated('motivo'));
        } catch (OperacionDeProveedorNoPermitida $error) {
            return back()->withErrors(['motivo' => $error->getMessage()]);
        }

        Inertia::flash('exito', "«{$proveedor->nombre}» queda retirado: ya no se le reevalúa.");

        return to_route('proveedores.show', $proveedor);
    }

    public function reactivar(Request $request, Proveedor $proveedor, CambiarEstadoProveedor $estado): RedirectResponse
    {
        /** @var User $quien */
        $quien = $request->user();

        try {
            $estado->reactivar($proveedor, $quien);
        } catch (OperacionDeProveedorNoPermitida $error) {
            return back()->withErrors(['proveedor' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Proveedor reactivado. Vuelve a «en evaluación»: lo evaluado antes de retirarlo ya no vale.');

        return to_route('proveedores.show', $proveedor);
    }

    public function guardarCertificacion(GuardarCertificacionProveedorRequest $request, Proveedor $proveedor): RedirectResponse
    {
        $proveedor->certificaciones()->create($request->validated());

        Inertia::flash('exito', 'Certificación registrada.');

        return back();
    }

    public function borrarCertificacion(Proveedor $proveedor, ProveedorCertificacion $certificacion): RedirectResponse
    {
        $certificacion->delete();

        Inertia::flash('exito', 'Certificación eliminada. Si tenía una evidencia, la evidencia sigue en su sitio.');

        return back();
    }

    public function derivarTarea(DerivarTareaDeProveedorRequest $request, Proveedor $proveedor, DerivarTareaDeProveedor $derivar): RedirectResponse
    {
        /** @var User $autor */
        $autor = $request->user();
        $tarea = $derivar($proveedor, $request->validated(), $autor);

        Inertia::flash('exito', "Tarea «{$tarea->titulo}» abierta.");

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Proveedor $proveedor): array
    {
        $organizacion = Organizacion::query()->findOrFail($proveedor->organizacion_id);
        $criticidad = $proveedor->criticidad();

        return [
            'id' => $proveedor->id,
            'codigo' => $proveedor->codigo,
            'nombre' => $proveedor->nombre,
            'cif' => $proveedor->cif,
            'servicio_prestado' => $proveedor->servicio_prestado,
            'estado' => $proveedor->estado->value,
            'estadoEtiqueta' => $proveedor->estado->etiqueta(),
            'estadoDescripcion' => $proveedor->estado->descripcion(),
            'estadoTono' => $proveedor->estado->tono(),
            'estadoIcono' => $proveedor->estado->icono(),
            'criticidad' => $criticidad->value,
            'criticidadEtiqueta' => $criticidad->etiqueta(),
            'criticidadTono' => $criticidad->tono(),
            'criticidad_derivada' => $proveedor->criticidad_derivada?->value,
            'criticidad_declarada' => $proveedor->criticidad_declarada?->value,
            'justificacion_criticidad' => $proveedor->justificacion_criticidad,
            'rebajaLaDerivada' => $proveedor->rebajaLaDerivada(),
            'mesesReevaluacion' => $organizacion->mesesReevaluacion($criticidad),
            'es_nube' => $proveedor->es_nube,
            'modelo_nube' => $proveedor->modelo_nube?->value,
            'modeloNubeEtiqueta' => $proveedor->modelo_nube?->etiqueta(),
            'ubicacion_datos' => $proveedor->ubicacion_datos->value,
            'ubicacionDatosEtiqueta' => $proveedor->ubicacion_datos->etiqueta(),
            'ubicacion_detalle' => $proveedor->ubicacion_detalle,
            'es_subencargado_rgpd' => $proveedor->es_subencargado_rgpd,
            'responsable_id' => $proveedor->responsable_id,
            'responsable' => $proveedor->responsable?->name,
            'notas' => $proveedor->notas,
            'proximaEvaluacion' => $proveedor->proxima_evaluacion?->toDateString(),
            'reevaluacionVencida' => $proveedor->proxima_evaluacion?->isPast() === true
                && ! $proveedor->proxima_evaluacion->isToday(),
            'seReevalua' => $proveedor->estado->seReevalua(),
        ];
    }

    /**
     * Las evaluaciones, de la más reciente a la más antigua, cada una con lo que
     * se vio cláusula a cláusula.
     *
     * @return list<array<string, mixed>>
     */
    private function evaluaciones(Proveedor $proveedor): array
    {
        return $proveedor->evaluaciones()
            ->with(['evaluadaPor:id,name', 'clausulas.clausula:id,codigo,titulo'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ProveedorEvaluacion $evaluacion): array => [
                'id' => $evaluacion->id,
                'fecha' => $evaluacion->fecha->toDateString(),
                'resultado' => $evaluacion->resultado->value,
                'resultadoEtiqueta' => $evaluacion->resultado->etiqueta(),
                'resultadoTono' => $evaluacion->resultado->tono(),
                'resultadoIcono' => $evaluacion->resultado->icono(),
                'criticidad' => $evaluacion->criticidad->etiqueta(),
                'conclusiones' => $evaluacion->conclusiones,
                'evaluadaPor' => $evaluacion->evaluadaPor?->name,
                'clausulas' => $evaluacion->clausulas
                    ->sortBy(fn (ProveedorEvaluacionClausula $una): string => (string) $una->clausula?->codigo)
                    ->map(fn (ProveedorEvaluacionClausula $una): array => [
                        'codigo' => (string) $una->clausula?->codigo,
                        'titulo' => (string) $una->clausula?->titulo,
                        'resultado' => $una->resultado->etiqueta(),
                        'tono' => $una->resultado->tono(),
                        'icono' => $una->resultado->icono(),
                        'nota' => $una->nota,
                    ])->values()->all(),
                'incumplidas' => $evaluacion->clausulas
                    ->filter(fn (ProveedorEvaluacionClausula $una): bool => $una->resultado === ResultadoClausula::NoCumple)
                    ->count(),
            ])->values()->all();
    }

    /**
     * @return list<array{id: int, codigo: string, titulo: string, descripcion: ?string, referencias: string}>
     */
    private function clausulasVigentes(): array
    {
        return ClausulaContractual::query()
            ->vigentes()
            ->get()
            ->map(fn (ClausulaContractual $clausula): array => [
                'id' => $clausula->id,
                'codigo' => $clausula->codigo,
                'titulo' => $clausula->titulo,
                'descripcion' => $clausula->descripcion,
                'referencias' => implode(', ', array_map(
                    static fn (array $referencia): string => $referencia['requisito'],
                    $clausula->referencias,
                )),
            ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(?int $responsableActual = null): array
    {
        return [
            'criticidades' => array_map(
                static fn (Criticidad $criticidad): array => ['valor' => $criticidad->value, 'etiqueta' => $criticidad->etiqueta()],
                Criticidad::cases(),
            ),
            'modelosNube' => array_map(
                static fn (ModeloNube $modelo): array => ['valor' => $modelo->value, 'etiqueta' => $modelo->etiqueta()],
                ModeloNube::cases(),
            ),
            'ubicaciones' => array_map(
                static fn (UbicacionDatos $ubicacion): array => ['valor' => $ubicacion->value, 'etiqueta' => $ubicacion->etiqueta()],
                UbicacionDatos::cases(),
            ),
            'resultados' => array_map(
                static fn (ResultadoEvaluacion $resultado): array => ['valor' => $resultado->value, 'etiqueta' => $resultado->etiqueta()],
                ResultadoEvaluacion::cases(),
            ),
            'responsables' => app(CuentasAsignables::class)->opciones(Permiso::ProveedoresGestionar, $responsableActual),
        ];
    }
}
