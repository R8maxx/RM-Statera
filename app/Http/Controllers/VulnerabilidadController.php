<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Incidente\Models\Incidente;
use App\Domain\Proveedor\Enums\EstadoProveedor;
use App\Domain\Proveedor\Models\Proveedor;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Usuario\CuentasAsignables;
use App\Domain\Vulnerabilidad\CambiarEstadoVulnerabilidad;
use App\Domain\Vulnerabilidad\CodigoVulnerabilidad;
use App\Domain\Vulnerabilidad\DerivarTareaDeVulnerabilidad;
use App\Domain\Vulnerabilidad\Enums\EstadoVulnerabilidad;
use App\Domain\Vulnerabilidad\Enums\OrigenVulnerabilidad;
use App\Domain\Vulnerabilidad\Enums\Severidad;
use App\Domain\Vulnerabilidad\Excepciones\OperacionDeVulnerabilidadNoPermitida;
use App\Domain\Vulnerabilidad\Models\Vulnerabilidad;
use App\Domain\Vulnerabilidad\Models\VulnerabilidadTransicion;
use App\Domain\Vulnerabilidad\PlazoRemediacion;
use App\Domain\Vulnerabilidad\RegistroVulnerabilidades;
use App\Http\Requests\CambiarEstadoVulnerabilidadRequest;
use App\Http\Requests\DerivarTareaDeVulnerabilidadRequest;
use App\Http\Requests\GuardarVulnerabilidadRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\VulnerabilidadRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El registro de vulnerabilidades: invariante 8, A.8.8 y `op.exp.4`.
 *
 * Valida, delega y devuelve. La severidad desde el CVSS, el plazo desde la
 * política y las reglas de cada transición viven en `app/Domain/Vulnerabilidad/`.
 */
class VulnerabilidadController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, VulnerabilidadRecurso $recurso, RegistroVulnerabilidades $registro): Response
    {
        return Inertia::render('vulnerabilidades/Index', [
            ...$this->tabla($recurso, $request),
            'alertas' => $registro->alertas(),
            'pendientes' => $registro->pendientes(),
            'total' => $registro->total(),
        ]);
    }

    /**
     * El alta, con un activo ya marcado si se llega desde su ficha: es de donde
     * sale la mitad de ellas, un sistema operativo sin soporte que avisa la
     * obsolescencia.
     */
    public function create(Request $request, CodigoVulnerabilidad $codigo): Response
    {
        $activo = $request->integer('activo') > 0
            ? Activo::query()->findOrFail($request->integer('activo'))
            : null;

        return Inertia::render('vulnerabilidades/Formulario', [
            'vulnerabilidad' => null,
            'sugerencia' => [
                'codigo' => $codigo->siguiente(),
                'activos' => $activo === null ? [] : [(string) $activo->id],
                'titulo' => $activo?->fin_soporte_so?->isPast() === true
                    ? "{$activo->sistema_operativo} fuera de soporte en {$activo->codigo}"
                    : null,
            ],
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarVulnerabilidadRequest $request, PlazoRemediacion $plazo): RedirectResponse
    {
        $vulnerabilidad = DB::transaction(function () use ($request, $plazo): Vulnerabilidad {
            $vulnerabilidad = Vulnerabilidad::query()->create($request->datos());
            $this->sincronizarActivos($vulnerabilidad, $request->activos());
            $plazo->recalcular($vulnerabilidad);

            VulnerabilidadTransicion::query()->create([
                'vulnerabilidad_id' => $vulnerabilidad->id,
                'estado_anterior' => null,
                'estado_nuevo' => EstadoVulnerabilidad::Abierta,
                'usuario_id' => $request->user()?->id,
            ]);

            return $vulnerabilidad;
        });

        $vulnerabilidad->refresh();

        Inertia::flash('exito', match (true) {
            $vulnerabilidad->fecha_limite === null => "«{$vulnerabilidad->codigo}» registrada. Es informativa: no tiene plazo.",
            // Detectada hace más de lo que da su severidad: llega ya vencida, y
            // un «antes del» con una fecha pasada se lee como una errata.
            $vulnerabilidad->fueraDePlazo() => "«{$vulnerabilidad->codigo}» registrada, y ya fuera de plazo: vencía el {$vulnerabilidad->fecha_limite->format('d/m/Y')}.",
            default => "«{$vulnerabilidad->codigo}» registrada. Hay que remediarla antes del {$vulnerabilidad->fecha_limite->format('d/m/Y')}.",
        });

        return to_route('vulnerabilidades.show', $vulnerabilidad);
    }

    public function show(Request $request, Vulnerabilidad $vulnerabilidad): Response
    {
        $vulnerabilidad->load(['activos', 'responsable:id,name', 'proveedor:id,codigo,nombre', 'riesgo:id,codigo,titulo',
            'incidente:id,codigo,titulo', 'aceptadaPor:id,name', 'verificadaPor:id,name', 'tareas']);

        $usuario = $request->user();

        return Inertia::render('vulnerabilidades/Ficha', [
            'vulnerabilidad' => [
                'id' => $vulnerabilidad->id,
                'codigo' => $vulnerabilidad->codigo,
                'titulo' => $vulnerabilidad->titulo,
                'descripcion' => $vulnerabilidad->descripcion,
                'cve' => $vulnerabilidad->cve,
                'cvss' => $vulnerabilidad->cvss_puntuacion,
                'vector' => $vulnerabilidad->cvss_vector,
                'severidad' => $vulnerabilidad->severidad->value,
                'severidadEtiqueta' => $vulnerabilidad->severidad->etiqueta(),
                'severidadTono' => $vulnerabilidad->severidad->tono(),
                'estado' => $vulnerabilidad->estado->value,
                'estadoEtiqueta' => $vulnerabilidad->estado->etiqueta(),
                'estadoTono' => $vulnerabilidad->estado->tono(),
                'estadoIcono' => $vulnerabilidad->estado->icono(),
                'origen' => $vulnerabilidad->origen->etiqueta(),
                'fechaDeteccion' => $vulnerabilidad->fecha_deteccion->toDateString(),
                'fechaLimite' => $vulnerabilidad->estado->correPlazo() ? $vulnerabilidad->fecha_limite?->toDateString() : null,
                'fueraDePlazo' => $vulnerabilidad->fueraDePlazo(),
                'responsable' => $vulnerabilidad->responsable?->name,
                'proveedor' => $vulnerabilidad->proveedor?->only(['id', 'codigo', 'nombre']),
                'riesgo' => $vulnerabilidad->riesgo?->only(['id', 'codigo', 'titulo']),
                'incidente' => $vulnerabilidad->incidente?->only(['id', 'codigo', 'titulo']),
                'remediacion' => $vulnerabilidad->remediacion,
                'motivoAceptacion' => $vulnerabilidad->motivo_aceptacion,
                'aceptadaPor' => $vulnerabilidad->aceptadaPor?->name,
                'aceptadaEn' => $vulnerabilidad->aceptada_en?->toIso8601String(),
                'verificacion' => $vulnerabilidad->verificacion,
                'verificadaPor' => $vulnerabilidad->verificadaPor?->name,
                'cerradaEn' => $vulnerabilidad->cerrada_en?->toIso8601String(),
            ],
            'activos' => $vulnerabilidad->activos
                ->sortBy('codigo')
                ->map(fn (Activo $activo): array => ['id' => $activo->id, 'codigo' => $activo->codigo, 'nombre' => $activo->nombre])
                ->values()->all(),
            'transiciones' => array_map(
                static fn (EstadoVulnerabilidad $estado): array => [
                    'valor' => $estado->value,
                    'etiqueta' => $estado->etiqueta(),
                    'tono' => $estado->tono(),
                    'icono' => $estado->icono(),
                    'exigeNota' => $estado->exigeMotivoDesde($vulnerabilidad->estado) || $estado === EstadoVulnerabilidad::Cerrada,
                ],
                array_values(array_filter(
                    $vulnerabilidad->estado->transicionesPermitidas(),
                    static fn (EstadoVulnerabilidad $estado): bool => $estado !== EstadoVulnerabilidad::Aceptada
                        || ($usuario?->can(Permiso::VulnerabilidadesAceptar->value) ?? false),
                )),
            ),
            'historial' => $vulnerabilidad->transiciones()
                ->with('usuario:id,name')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (VulnerabilidadTransicion $transicion): array => [
                    'id' => $transicion->id,
                    'anterior' => $transicion->estado_anterior?->etiqueta(),
                    'nuevo' => $transicion->estado_nuevo->etiqueta(),
                    'tono' => $transicion->estado_nuevo->tono(),
                    'icono' => $transicion->estado_nuevo->icono(),
                    'usuario' => $transicion->usuario?->name,
                    'nota' => $transicion->nota,
                    'fecha' => $transicion->created_at->toIso8601String(),
                ])->values()->all(),
            'tareas' => $vulnerabilidad->tareas
                ->map(fn (Tarea $tarea): array => [
                    'id' => $tarea->id,
                    'titulo' => $tarea->titulo,
                    'estado' => $tarea->estado->etiqueta(),
                    'tono' => $tarea->estado->tono(),
                    'icono' => $tarea->estado->icono(),
                ])->values()->all(),
            'responsables' => app(CuentasAsignables::class)->opciones(Permiso::TareasGestionar),
            'prioridades' => array_map(
                static fn (PrioridadTarea $prioridad): array => ['valor' => $prioridad->value, 'etiqueta' => $prioridad->etiqueta()],
                PrioridadTarea::cases(),
            ),
            'puedeGestionar' => $usuario?->can(Permiso::VulnerabilidadesGestionar->value) ?? false,
            'puedeAbrirTarea' => $usuario?->can(Permiso::TareasGestionar->value) ?? false,
        ]);
    }

    public function edit(Vulnerabilidad $vulnerabilidad): Response
    {
        return Inertia::render('vulnerabilidades/Formulario', [
            'vulnerabilidad' => [
                'id' => $vulnerabilidad->id,
                'codigo' => $vulnerabilidad->codigo,
                'titulo' => $vulnerabilidad->titulo,
                'descripcion' => $vulnerabilidad->descripcion,
                'cve' => $vulnerabilidad->cve,
                'cvss_puntuacion' => $vulnerabilidad->cvss_puntuacion,
                'cvss_vector' => $vulnerabilidad->cvss_vector,
                'severidad' => $vulnerabilidad->severidad->value,
                'origen' => $vulnerabilidad->origen->value,
                'fecha_deteccion' => $vulnerabilidad->fecha_deteccion->toDateString(),
                'activos' => $vulnerabilidad->activos()->pluck('activos.id')->map(fn (int $id): string => (string) $id)->all(),
                'proveedor_id' => $vulnerabilidad->proveedor_id,
                'riesgo_id' => $vulnerabilidad->riesgo_id,
                'incidente_id' => $vulnerabilidad->incidente_id,
                'responsable_id' => $vulnerabilidad->responsable_id,
                'remediacion' => $vulnerabilidad->remediacion,
            ],
            'sugerencia' => null,
            ...$this->opciones($vulnerabilidad->responsable_id),
        ]);
    }

    public function update(GuardarVulnerabilidadRequest $request, Vulnerabilidad $vulnerabilidad, PlazoRemediacion $plazo): RedirectResponse
    {
        DB::transaction(function () use ($request, $vulnerabilidad, $plazo): void {
            $vulnerabilidad->update($request->datos());
            $this->sincronizarActivos($vulnerabilidad, $request->activos());
            // La severidad o la fecha pueden haber cambiado, y con ellas el plazo.
            $plazo->recalcular($vulnerabilidad);
        });

        Inertia::flash('exito', 'Vulnerabilidad actualizada.');

        return to_route('vulnerabilidades.show', $vulnerabilidad);
    }

    public function transicion(
        CambiarEstadoVulnerabilidadRequest $request,
        Vulnerabilidad $vulnerabilidad,
        CambiarEstadoVulnerabilidad $cambiar,
    ): RedirectResponse {
        /** @var User $quien */
        $quien = $request->user();

        try {
            $cambiar(
                $vulnerabilidad,
                EstadoVulnerabilidad::from((string) $request->validated('estado')),
                $quien,
                $request->validated('nota'),
            );
        } catch (OperacionDeVulnerabilidadNoPermitida $error) {
            return back()->withErrors(['nota' => $error->getMessage()]);
        }

        Inertia::flash('exito', "{$vulnerabilidad->codigo}: {$vulnerabilidad->fresh()?->estado->etiqueta()}.");

        return back();
    }

    public function derivarTarea(
        DerivarTareaDeVulnerabilidadRequest $request,
        Vulnerabilidad $vulnerabilidad,
        DerivarTareaDeVulnerabilidad $derivar,
    ): RedirectResponse {
        /** @var User $autor */
        $autor = $request->user();
        $tarea = $derivar($vulnerabilidad, $request->validated(), $autor);

        Inertia::flash('exito', "Tarea «{$tarea->titulo}» abierta.");

        return back();
    }

    /**
     * Los activos van por su pivote, con `organizacion_id` a mano: `sync` no
     * rellena columnas extra.
     *
     * @param  list<int>  $activos
     */
    private function sincronizarActivos(Vulnerabilidad $vulnerabilidad, array $activos): void
    {
        // Sólo los que se ven: con el scope puesto, un id ajeno no se encuentra.
        $validos = Activo::query()->whereKey($activos)->pluck('id')->all();

        $vulnerabilidad->activos()->sync(array_fill_keys(
            $validos,
            ['organizacion_id' => $vulnerabilidad->organizacion_id],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(?int $responsableActual = null): array
    {
        return [
            'severidades' => array_map(
                static fn (Severidad $severidad): array => ['valor' => $severidad->value, 'etiqueta' => $severidad->etiqueta()],
                Severidad::cases(),
            ),
            'origenes' => array_map(
                static fn (OrigenVulnerabilidad $origen): array => ['valor' => $origen->value, 'etiqueta' => $origen->etiqueta()],
                OrigenVulnerabilidad::cases(),
            ),
            'activos' => Activo::query()
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre'])
                ->map(fn (Activo $activo): array => ['valor' => (string) $activo->id, 'etiqueta' => "{$activo->codigo} · {$activo->nombre}"])
                ->values()->all(),
            'proveedores' => Proveedor::query()
                ->where('estado', '<>', EstadoProveedor::Retirado->value)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre'])
                ->map(fn (Proveedor $proveedor): array => ['valor' => (string) $proveedor->id, 'etiqueta' => "{$proveedor->codigo} · {$proveedor->nombre}"])
                ->values()->all(),
            'riesgos' => Riesgo::query()
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'titulo'])
                ->map(fn (Riesgo $riesgo): array => ['valor' => (string) $riesgo->id, 'etiqueta' => "{$riesgo->codigo} · {$riesgo->titulo}"])
                ->values()->all(),
            'incidentes' => Incidente::query()
                ->orderByDesc('fecha_deteccion')
                ->limit(200)
                ->get(['id', 'codigo', 'titulo'])
                ->map(fn (Incidente $incidente): array => ['valor' => (string) $incidente->id, 'etiqueta' => "{$incidente->codigo} · {$incidente->titulo}"])
                ->values()->all(),
            'responsables' => app(CuentasAsignables::class)->opciones(Permiso::VulnerabilidadesGestionar, $responsableActual),
            'hoy' => Carbon::today()->toDateString(),
        ];
    }
}
