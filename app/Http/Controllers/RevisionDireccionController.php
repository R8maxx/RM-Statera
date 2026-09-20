<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\RevisionDireccion\AbrirDecision;
use App\Domain\RevisionDireccion\AprobarRevision;
use App\Domain\RevisionDireccion\CambiarEstadoRevision;
use App\Domain\RevisionDireccion\CodigoRevision;
use App\Domain\RevisionDireccion\EntradasRevision;
use App\Domain\RevisionDireccion\Enums\EstadoRevision;
use App\Domain\RevisionDireccion\Excepciones\RevisionNoAprobable;
use App\Domain\RevisionDireccion\Excepciones\TransicionDeRevisionNoPermitida;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use App\Domain\RevisionDireccion\RegistrarRevision;
use App\Domain\RevisionDireccion\VincularDecision;
use App\Domain\Tarea\Coste;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\Plazo;
use App\Http\Requests\AbrirDecisionRequest;
use App\Http\Requests\CambiarEstadoRevisionRequest;
use App\Http\Requests\GuardarRevisionDireccionRequest;
use App\Http\Requests\VincularDecisionRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\RevisionDireccionRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La revisión por la dirección: § 4.15 y la cláusula 9.3.
 *
 * Es el módulo que llevaba bloqueado desde el principio, y no por su complejidad:
 * la 9.3 cierra la lista de entradas obligatorias y dos de las siete no salían de
 * ninguna parte. Con la 6.2 y la 10.1 dentro, las siete existen.
 *
 * **La ficha enseña las entradas EN VIVO mientras la revisión está abierta, y las
 * congeladas cuando está aprobada.** Es la distinción que hace el módulo: antes de
 * firmar, lo que se mira es cómo está la cosa hoy —que es para lo que se convoca
 * la reunión—; después, lo que se mira es lo que se revisó aquel día. Mezclar las
 * dos haría que el acta cambiara sola.
 *
 * **Aprobar tiene ruta y permiso propios**, y no pasa por la de transición: no es
 * un cambio de estado, es el acto que congela las entradas y estampa la firma. La
 * cláusula se llama «revisión por la **dirección**», así que quién firma no es un
 * matiz de permisos.
 */
class RevisionDireccionController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, RevisionDireccionRecurso $recurso): Response
    {
        return Inertia::render('revision-direccion/Index', [
            ...$this->tabla($recurso, $request),
            'total' => RevisionDireccion::query()->count(),
            'sinFirmar' => RevisionDireccion::query()->abiertas()->count(),
        ]);
    }

    public function create(CodigoRevision $codigos): Response
    {
        return Inertia::render('revision-direccion/Formulario', [
            'revision' => null,
            'sugerencia' => [
                'codigo' => $codigos->siguiente(),
                ...$this->periodoPropuesto(),
            ],
        ]);
    }

    public function store(GuardarRevisionDireccionRequest $request, RegistrarRevision $registrar): RedirectResponse
    {
        $revision = $registrar($request->validated());

        Inertia::flash('exito', "Revisión {$revision->codigo} convocada.");

        return to_route('revision-direccion.show', $revision);
    }

    public function show(RevisionDireccion $revision_direccion, EntradasRevision $entradas): Response
    {
        $revision_direccion->load(['aprobadaPor', 'tareas.responsable']);

        /*
         * En vivo mientras se prepara, congeladas cuando está firmada. Ver la
         * cabecera: es la distinción que hace el módulo, y consultar en vivo un
         * acta aprobada haría que enseñara las cifras de hoy bajo la fecha de la
         * reunión del año pasado.
         */
        $recogidas = $revision_direccion->estado === EstadoRevision::Aprobada
            ? ($revision_direccion->instantanea ?? [])
            : $entradas->para($revision_direccion);

        return Inertia::render('revision-direccion/Ficha', [
            'revision' => $this->serializar($revision_direccion),
            'entradas' => $recogidas,
            'congeladas' => $revision_direccion->estado === EstadoRevision::Aprobada,
            'decisiones' => $revision_direccion->tareas
                ->map(fn (Tarea $tarea): array => $this->serializarDecision($tarea))
                ->values()
                ->all(),
            'coste' => [
                'total' => Coste::escribir(Coste::total($revision_direccion->tareas->all())),
                'sinEstimar' => Coste::sinEstimar($revision_direccion->tareas->all()),
            ],
            'transiciones' => array_map(
                static fn (EstadoRevision $destino): array => [
                    'valor' => $destino->value,
                    'etiqueta' => $destino->etiqueta(),
                    'tono' => $destino->tono(),
                    'icono' => $destino->icono(),
                ],
                // Aprobar se ofrece aparte, con su botón y su permiso.
                array_values(array_filter(
                    $revision_direccion->estado->transicionesPermitidas(),
                    static fn (EstadoRevision $destino): bool => $destino !== EstadoRevision::Aprobada,
                )),
            ),
            'puedeAprobar' => $this->puede(Permiso::RevisionDireccionAprobar)
                && $revision_direccion->estado === EstadoRevision::EnCurso,
            'puedeGestionar' => $this->puede(Permiso::RevisionDireccionGestionar),
            'prioridades' => array_map(
                static fn (PrioridadTarea $prioridad): array => [
                    'valor' => $prioridad->value,
                    'etiqueta' => $prioridad->etiqueta(),
                ],
                PrioridadTarea::cases(),
            ),
            ...$this->opciones(),
        ]);
    }

    public function edit(RevisionDireccion $revision_direccion): Response
    {
        return Inertia::render('revision-direccion/Formulario', [
            'revision' => $this->serializar($revision_direccion),
            'sugerencia' => null,
        ]);
    }

    public function update(
        GuardarRevisionDireccionRequest $request,
        RevisionDireccion $revision_direccion,
    ): RedirectResponse {
        /*
         * La guarda está aquí aunque el trigger también lo impida, por el mismo
         * motivo que en el cierre de una auditoría: un `update` sobre una fila
         * blindada sube como `QueryException` sin capturar, el usuario ve el 500
         * genérico y el mensaje de la base —sin tildes, porque es SQL— no lo lee
         * nadie.
         */
        if (! $revision_direccion->admiteCambios()) {
            return back()->withErrors([
                'codigo' => 'El acta está aprobada y no se modifica. Para corregirla hay que reabrir la revisión.',
            ]);
        }

        $revision_direccion->update($request->validated());

        Inertia::flash('exito', 'Revisión actualizada.');

        return to_route('revision-direccion.show', $revision_direccion);
    }

    public function destroy(RevisionDireccion $revision_direccion): RedirectResponse
    {
        $codigo = $revision_direccion->codigo;
        $revision_direccion->delete();

        Inertia::flash('exito', "Revisión {$codigo} eliminada.");

        return to_route('revision-direccion.index');
    }

    // --- El ciclo de la cláusula 9.3 ----------------------------------------

    public function transicion(
        CambiarEstadoRevisionRequest $request,
        RevisionDireccion $revision_direccion,
        CambiarEstadoRevision $cambiar,
    ): RedirectResponse {
        try {
            $cambiar($revision_direccion, EstadoRevision::from((string) $request->validated('estado')));
        } catch (TransicionDeRevisionNoPermitida $error) {
            return back()->withErrors(['estado' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Revisión actualizada.');

        return back();
    }

    /**
     * Firma el acta: congela las siete entradas y vuelve la fila inmutable.
     *
     * Ruta propia y permiso propio. Es el único gesto del módulo que importa.
     */
    public function aprobar(
        Request $request,
        RevisionDireccion $revision_direccion,
        AprobarRevision $aprobar,
    ): RedirectResponse {
        $usuario = $request->user();

        if (! $usuario instanceof User) {
            abort(403);
        }

        try {
            $aprobar($revision_direccion, $usuario);
        } catch (RevisionNoAprobable $error) {
            return back()->withErrors(['estado' => $error->getMessage()]);
        }

        Inertia::flash('exito', "Acta de {$revision_direccion->codigo} aprobada. Las entradas quedan congeladas.");

        return back();
    }

    // --- Las salidas (9.3.3) ------------------------------------------------

    public function abrirDecision(
        AbrirDecisionRequest $request,
        RevisionDireccion $revision_direccion,
        AbrirDecision $abrir,
    ): RedirectResponse {
        $tarea = $abrir($revision_direccion, $request->validated(), $request->user());

        Inertia::flash('exito', "Decisión «{$tarea->titulo}» registrada.");

        return back();
    }

    public function vincularDecision(
        VincularDecisionRequest $request,
        RevisionDireccion $revision_direccion,
        VincularDecision $vincular,
    ): RedirectResponse {
        $tarea = Tarea::query()->findOrFail($request->validated('tarea_id'));

        $vincular->vincular($revision_direccion, $tarea, $request->user());

        Inertia::flash('exito', 'Decisión vinculada.');

        return back();
    }

    public function desvincularDecision(
        RevisionDireccion $revision_direccion,
        Tarea $tarea,
        VincularDecision $vincular,
    ): RedirectResponse {
        $vincular->desvincular($revision_direccion, $tarea);

        Inertia::flash('exito', 'Decisión desvinculada. La tarea sigue en el plan de acción.');

        return back();
    }

    /**
     * El periodo que se propone al convocar: desde el día siguiente al fin de la
     * última revisión aprobada, hasta hoy.
     *
     * **Propone y no impone**, como el código. Es lo que hace que dos revisiones
     * consecutivas no dejen un hueco sin revisar, que es la pregunta obvia de un
     * auditor cuando ve dos actas seguidas.
     *
     * @return array<string, string>
     */
    private function periodoPropuesto(): array
    {
        $anterior = RevisionDireccion::query()->aprobadas()->orderByDesc('fecha')->first();

        $desde = $anterior instanceof RevisionDireccion
            ? $anterior->periodo_hasta->copy()->addDay()
            : now()->startOfYear();

        return [
            'periodo_desde' => $desde->toDateString(),
            'periodo_hasta' => now()->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(RevisionDireccion $revision): array
    {
        return [
            'id' => $revision->id,
            'codigo' => $revision->codigo,
            'fecha' => $revision->fecha->toDateString(),
            'fechaLarga' => $revision->fecha->format('d/m/Y'),
            'periodo_desde' => $revision->periodo_desde->toDateString(),
            'periodo_hasta' => $revision->periodo_hasta->toDateString(),
            'periodo' => $revision->periodo(),
            'asistentes' => $revision->asistentes,
            'conclusiones' => $revision->conclusiones,
            'estado' => $revision->estado->value,
            'estadoEtiqueta' => $revision->estado->etiqueta(),
            'estadoTono' => $revision->estado->tono(),
            'estadoIcono' => $revision->estado->icono(),
            'admiteCambios' => $revision->admiteCambios(),
            'aprobadaPor' => $revision->aprobadaPor?->name,
            'aprobadaEn' => $revision->aprobada_en?->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarDecision(Tarea $tarea): array
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
     * Los responsables, acotados a la organización a mano: `User` no lleva
     * `PerteneceAOrganizacion`, así que aquí no hay scope global ni RLS que tapen
     * el cruce.
     *
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
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
