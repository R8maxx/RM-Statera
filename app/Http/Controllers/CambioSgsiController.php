<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Cambio\AbrirActuacionDeCambio;
use App\Domain\Cambio\CambiarEstadoCambio;
use App\Domain\Cambio\CodigoCambio;
use App\Domain\Cambio\Enums\AmbitoCambio;
use App\Domain\Cambio\Enums\EstadoCambio;
use App\Domain\Cambio\Enums\OrigenCambio;
use App\Domain\Cambio\Excepciones\TransicionDeCambioNoPermitida;
use App\Domain\Cambio\Models\CambioSgsi;
use App\Domain\Cambio\Models\CambioSgsiTransicion;
use App\Domain\Cambio\RegistrarCambio;
use App\Domain\Cambio\RegistroCambios;
use App\Domain\Cambio\VincularActuacionDeCambio;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Tarea\Coste;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\Plazo;
use App\Http\Requests\AbrirActuacionDeCambioSgsiRequest;
use App\Http\Requests\CambiarEstadoCambioSgsiRequest;
use App\Http\Requests\GuardarCambioSgsiRequest;
use App\Http\Requests\VincularActuacionDeCambioSgsiRequest;
use App\Http\Resources\CambioSgsiRecurso;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El registro de cambios del SGSI: la cláusula 6.3.
 *
 * **Tres permisos**, como objetivos: quien lleva el sistema propone, planifica y
 * revisa; quien lo firma compromete a la organización con el cambio. Aprobar —y
 * renunciar a un cambio ya aprobado— va con `cambios_sgsi.aprobar`, y se
 * comprueba aquí y no en la ruta porque la ruta de transición es una sola.
 */
class CambioSgsiController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, CambioSgsiRecurso $recurso, RegistroCambios $registro): Response
    {
        return Inertia::render('cambios-sgsi/Index', [
            ...$this->tabla($recurso, $request),
            'alertas' => $registro->alertas(),
            'pendientes' => $registro->pendientes(),
            'total' => $registro->total(),
        ]);
    }

    public function create(Request $request, CodigoCambio $codigos): Response
    {
        /*
         * El origen se puede traer puesto —`?origen=revision_direccion` desde el
         * acta, porque la 9.3 produce «necesidades de cambio»—, y sólo el origen:
         * sin clave foránea, el mismo reparto que una mejora que sale de una
         * revisión.
         */
        $origen = OrigenCambio::tryFrom((string) $request->query('origen')) ?? OrigenCambio::Propio;

        return Inertia::render('cambios-sgsi/Formulario', [
            'cambio' => null,
            'sugerencia' => [
                'codigo' => $codigos->siguiente(),
                'origen' => $origen->value,
                'fecha_propuesta' => now()->toDateString(),
            ],
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarCambioSgsiRequest $request, RegistrarCambio $registrar): RedirectResponse
    {
        $cambio = $registrar($request->validated(), $request->user());

        Inertia::flash('exito', "Cambio {$cambio->codigo} registrado.");

        return to_route('cambios-sgsi.show', $cambio);
    }

    public function show(CambioSgsi $cambio): Response
    {
        $cambio->load(['responsable', 'aprobadoPor', 'tareas.responsable', 'transiciones.usuario']);

        return Inertia::render('cambios-sgsi/Ficha', [
            'cambio' => $this->serializar($cambio),
            'actuaciones' => $cambio->tareas
                ->map(fn (Tarea $tarea): array => $this->serializarActuacion($tarea))
                ->values()
                ->all(),
            'coste' => [
                'total' => Coste::escribir(Coste::total($cambio->tareas->all())),
                'sinEstimar' => Coste::sinEstimar($cambio->tareas->all()),
            ],
            'transiciones' => array_map(
                fn (EstadoCambio $destino): array => [
                    'valor' => $destino->value,
                    'etiqueta' => $destino->etiqueta(),
                    'tono' => $destino->tono(),
                    'icono' => $destino->icono(),
                    'exigeMotivo' => $destino->exigeNota($cambio->estado),
                    'permiso' => $destino->exigeAprobar($cambio->estado)
                        ? Permiso::CambiosSgsiAprobar->value
                        : Permiso::CambiosSgsiGestionar->value,
                ],
                $cambio->estado->transicionesPermitidas(),
            ),
            'historial' => $cambio->transiciones
                ->map(fn (CambioSgsiTransicion $transicion): array => [
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
            'prioridades' => array_map(
                static fn (PrioridadTarea $prioridad): array => [
                    'valor' => $prioridad->value,
                    'etiqueta' => $prioridad->etiqueta(),
                ],
                PrioridadTarea::cases(),
            ),
            'puedeGestionar' => $this->puede(Permiso::CambiosSgsiGestionar),
            'puedeAprobar' => $this->puede(Permiso::CambiosSgsiAprobar),
            ...$this->opciones(),
        ]);
    }

    public function edit(CambioSgsi $cambio): Response
    {
        return Inertia::render('cambios-sgsi/Formulario', [
            'cambio' => $this->serializar($cambio),
            'sugerencia' => null,
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarCambioSgsiRequest $request, CambioSgsi $cambio): RedirectResponse
    {
        $cambio->update($request->validated());

        Inertia::flash('exito', 'Cambio actualizado.');

        return to_route('cambios-sgsi.show', $cambio);
    }

    public function destroy(CambioSgsi $cambio): RedirectResponse
    {
        $codigo = $cambio->codigo;
        $cambio->delete();

        Inertia::flash('exito', "Cambio {$codigo} eliminado.");

        return to_route('cambios-sgsi.index');
    }

    // --- El ciclo de la cláusula 6.3 ----------------------------------------

    public function transicion(
        CambiarEstadoCambioSgsiRequest $request,
        CambioSgsi $cambio,
        CambiarEstadoCambio $cambiar,
    ): RedirectResponse {
        $destino = EstadoCambio::from((string) $request->validated('estado'));

        // Aprobar, y renunciar a lo aprobado, es de quien firma. Ver la cabecera.
        if ($destino->exigeAprobar($cambio->estado) && ! $this->puede(Permiso::CambiosSgsiAprobar)) {
            abort(403);
        }

        try {
            $cambiar(
                $cambio,
                $destino,
                $request->user(),
                $request->string('nota')->value() ?: null,
            );
        } catch (TransicionDeCambioNoPermitida $error) {
            return back()->withErrors(['estado' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Cambio actualizado.');

        return back();
    }

    // --- Lo que se va a hacer -----------------------------------------------

    public function abrirActuacion(
        AbrirActuacionDeCambioSgsiRequest $request,
        CambioSgsi $cambio,
        AbrirActuacionDeCambio $abrir,
    ): RedirectResponse {
        $tarea = $abrir($cambio, $request->validated(), $request->user());

        Inertia::flash('exito', "Actuación «{$tarea->titulo}» abierta.");

        return back();
    }

    public function vincularActuacion(
        VincularActuacionDeCambioSgsiRequest $request,
        CambioSgsi $cambio,
        VincularActuacionDeCambio $vincular,
    ): RedirectResponse {
        $tarea = Tarea::query()->findOrFail($request->validated('tarea_id'));

        $vincular->vincular($cambio, $tarea, $request->user());

        Inertia::flash('exito', 'Actuación vinculada.');

        return back();
    }

    public function desvincularActuacion(
        CambioSgsi $cambio,
        Tarea $tarea,
        VincularActuacionDeCambio $vincular,
    ): RedirectResponse {
        $vincular->desvincular($cambio, $tarea);

        Inertia::flash('exito', 'Actuación desvinculada. La tarea sigue en el plan de acción.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(CambioSgsi $cambio): array
    {
        $plazo = CambioSgsiRecurso::plazo($cambio);

        return [
            'id' => $cambio->id,
            'codigo' => $cambio->codigo,
            'titulo' => $cambio->titulo,
            'descripcion' => $cambio->descripcion,
            'ambito' => $cambio->ambito->value,
            'ambitoEtiqueta' => $cambio->ambito->etiqueta(),
            'origen' => $cambio->origen->value,
            'origenEtiqueta' => $cambio->origen->etiqueta(),
            'proposito' => $cambio->proposito,
            'consecuencias' => $cambio->consecuencias,
            'integridad' => $cambio->integridad,
            'recursos' => $cambio->recursos,
            'estado' => $cambio->estado->value,
            'estadoEtiqueta' => $cambio->estado->etiqueta(),
            'estadoTono' => $cambio->estado->tono(),
            'estadoIcono' => $cambio->estado->icono(),
            'responsable_id' => $cambio->responsable_id,
            'responsable' => $cambio->responsable?->name,
            'fecha_propuesta' => $cambio->fecha_propuesta->toDateString(),
            'fecha_prevista' => $cambio->fecha_prevista?->toDateString(),
            'fechaImplantacion' => $cambio->fecha_implantacion?->format('d/m/Y'),
            'fechaCierre' => $cambio->fecha_cierre?->format('d/m/Y'),
            'aprobadoPor' => $cambio->aprobadoPor?->name,
            'aprobadoEn' => $cambio->aprobado_en?->format('d/m/Y H:i'),
            'revision' => $cambio->revision,
            'plazoEtiqueta' => $plazo['etiqueta'],
            'plazoTono' => $plazo['tono'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarActuacion(Tarea $tarea): array
    {
        $plazo = Plazo::de($tarea);

        return [
            'id' => $tarea->id,
            'titulo' => $tarea->titulo,
            'estado' => $tarea->estado->value,
            'estadoEtiqueta' => $tarea->estado->etiqueta(),
            'estadoTono' => $tarea->estado->tono(),
            'estadoIcono' => $tarea->estado->icono(),
            'responsable' => $tarea->responsable?->name,
            'plazoEtiqueta' => $plazo->etiqueta,
            'plazoTono' => $plazo->tono,
            'fecha' => $plazo->fecha,
            'coste' => Coste::deLaTarea($tarea),
        ];
    }

    /**
     * Las opciones de los desplegables.
     *
     * Los responsables van acotados a la organización a mano: `User` no lleva
     * `PerteneceAOrganizacion`.
     *
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'ambitos' => array_map(
                static fn (AmbitoCambio $ambito): array => ['valor' => $ambito->value, 'etiqueta' => $ambito->etiqueta()],
                AmbitoCambio::cases(),
            ),
            'origenes' => array_map(
                static fn (OrigenCambio $origen): array => ['valor' => $origen->value, 'etiqueta' => $origen->etiqueta()],
                OrigenCambio::cases(),
            ),
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

    private function puede(Permiso $permiso): bool
    {
        return request()->user()?->can($permiso->value) ?? false;
    }
}
