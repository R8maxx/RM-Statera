<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Incidente\Models\Incidente;
use App\Domain\NoConformidad\AbrirAccionCorrectiva;
use App\Domain\NoConformidad\CambiarEstadoNoConformidad;
use App\Domain\NoConformidad\CodigoNoConformidad;
use App\Domain\NoConformidad\Enums\EstadoNoConformidad;
use App\Domain\NoConformidad\Enums\OrigenNoConformidad;
use App\Domain\NoConformidad\Excepciones\TransicionDeNoConformidadNoPermitida;
use App\Domain\NoConformidad\Models\NoConformidad;
use App\Domain\NoConformidad\Models\NoConformidadTransicion;
use App\Domain\NoConformidad\RegistrarNoConformidad;
use App\Domain\NoConformidad\RegistroNoConformidades;
use App\Domain\NoConformidad\VincularAccionCorrectiva;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Tarea\Coste;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\Plazo;
use App\Http\Requests\AbrirAccionCorrectivaRequest;
use App\Http\Requests\CambiarEstadoNoConformidadRequest;
use App\Http\Requests\GuardarNoConformidadRequest;
use App\Http\Requests\VincularAccionCorrectivaRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\NoConformidadRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El registro de no conformidades: § 4.13, la otra mitad del módulo de auditorías.
 *
 * Una no conformidad se abre **desde un hallazgo** —que es el camino que la deja
 * trazable— o suelta, para lo que no salió de una auditoría registrada en
 * Statera. El primero llega por `?hallazgo=`, con la descripción, el origen y la
 * fecha de detección ya puestos: pedir que se reescriban a mano es cómo se acaba
 * con dos versiones del mismo hecho.
 *
 * Las acciones correctivas **son tareas** y se manejan desde aquí, no desde
 * `/tareas`: abrirlas por el camino general obligaría a elegir un origen que no
 * se ofrece y dejaría el vínculo a medias.
 */
class NoConformidadController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, NoConformidadRecurso $recurso, RegistroNoConformidades $registro): Response
    {
        return Inertia::render('no-conformidades/Index', [
            ...$this->tabla($recurso, $request),
            'alertas' => $registro->alertas(),
            'pendientes' => $registro->pendientes(),
            'total' => $registro->total(),
        ]);
    }

    public function create(Request $request, CodigoNoConformidad $codigos): Response|RedirectResponse
    {
        $hallazgo = $this->hallazgoDe($request);
        $incidente = $this->incidenteDe($request);

        /*
         * Y desde el § 4.10, la tercera puerta: un incidente se trata una vez, y
         * lo impone el índice único sobre `incidente_id` — espejo exacto del de
         * `hallazgo_id`.
         */
        if ($incidente?->noConformidad !== null) {
            Inertia::flash('aviso', 'Ese incidente ya tiene su no conformidad.');

            return to_route('no-conformidades.show', $incidente->noConformidad);
        }

        /*
         * Un hallazgo se trata una vez, y el índice único de la tabla lo impone.
         * Sin esta puerta, el formulario se abriría y el alta reventaría con un
         * error de clave duplicada al final del trabajo; es más barato llevar a
         * la que ya existe.
         */
        if ($hallazgo?->noConformidad !== null) {
            Inertia::flash('aviso', 'Ese hallazgo ya tiene su no conformidad.');

            return to_route('no-conformidades.show', $hallazgo->noConformidad);
        }

        /*
         * Y desde la cláusula 10.1, la otra puerta: una oportunidad de mejora no
         * se trata aquí. Se lleva a su registro con el hallazgo puesto en vez de
         * dejar rellenar un formulario que el dominio va a rechazar al final.
         */
        if ($hallazgo !== null && ! $hallazgo->tipo->admiteNoConformidad()) {
            Inertia::flash('aviso', 'Una oportunidad de mejora se trata en su propio registro (cláusula 10.1).');

            return redirect()->to("/mejoras/crear?hallazgo={$hallazgo->id}");
        }

        return Inertia::render('no-conformidades/Formulario', [
            'noConformidad' => null,
            'hallazgo' => $hallazgo === null ? null : $this->serializarHallazgo($hallazgo),
            'incidente' => $incidente === null ? null : $this->serializarIncidente($incidente),
            'sugerencia' => [
                'codigo' => $codigos->siguiente($hallazgo?->auditoria->fecha),
                'origen' => match (true) {
                    $hallazgo !== null => OrigenNoConformidad::Auditoria->value,
                    $incidente !== null => OrigenNoConformidad::Incidente->value,
                    default => OrigenNoConformidad::Propia->value,
                },
                'descripcion' => match (true) {
                    $hallazgo !== null => $hallazgo->descripcion,
                    $incidente !== null => $incidente->descripcion,
                    default => null,
                },
                'fecha_deteccion' => match (true) {
                    $hallazgo !== null => $hallazgo->auditoria->fecha->toDateString(),
                    $incidente !== null => $incidente->fecha_deteccion->toDateString(),
                    default => now()->toDateString(),
                },
            ],
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarNoConformidadRequest $request, RegistrarNoConformidad $registrar): RedirectResponse
    {
        $datos = $request->validated();

        /*
         * El hallazgo se resuelve por el modelo y no se copia el id a pelo: así
         * pasa por el scope de organización, que es lo que impide colgar una no
         * conformidad de un hallazgo de otro cliente pasando su id a mano.
         */
        if (($datos['hallazgo_id'] ?? null) !== null) {
            $datos['hallazgo_id'] = Hallazgo::query()->findOrFail($datos['hallazgo_id'])->id;
        }

        // Y el incidente igual, por el mismo motivo.
        if (($datos['incidente_id'] ?? null) !== null) {
            $datos['incidente_id'] = Incidente::query()->findOrFail($datos['incidente_id'])->id;
        }

        $noConformidad = $registrar($datos, $request->user());

        Inertia::flash('exito', "No conformidad {$noConformidad->codigo} registrada.");

        return to_route('no-conformidades.show', $noConformidad);
    }

    public function show(NoConformidad $no_conformidad): Response
    {
        $no_conformidad->load([
            'responsable',
            'verificadaPor',
            'hallazgo.auditoria',
            'hallazgo.punto.implantacion.requisito',
            'pruebaContinuidad',
            'tareas.responsable',
            'transiciones.usuario',
        ]);

        return Inertia::render('no-conformidades/Ficha', [
            'noConformidad' => $this->serializar($no_conformidad),
            'pruebaContinuidad' => $no_conformidad->pruebaContinuidad === null ? null : [
                'id' => $no_conformidad->pruebaContinuidad->id,
                'codigo' => $no_conformidad->pruebaContinuidad->codigo,
            ],
            'hallazgo' => $no_conformidad->hallazgo === null
                ? null
                : $this->serializarHallazgo($no_conformidad->hallazgo),
            'acciones' => $no_conformidad->tareas
                ->map(fn (Tarea $tarea): array => $this->serializarAccion($tarea))
                ->values()
                ->all(),
            /*
             * El coste se cuenta **sobre tareas distintas**, como en el plan de
             * adecuación: una acción correctiva que cubre tres medidas se
             * presupuesta una vez. Y viaja con cuántas van sin estimar, que es el
             * denominador sin el cual «1.200 €» se lee como el coste total.
             */
            'coste' => [
                'total' => Coste::escribir(Coste::total($no_conformidad->tareas->all())),
                'sinEstimar' => Coste::sinEstimar($no_conformidad->tareas->all()),
            ],
            /*
             * Las transiciones las manda el servidor para que el cliente no
             * reconstruya la máquina de estados: un gesto que se acepta y luego
             * falla se explica mucho peor que uno que no se ofrece.
             */
            'transiciones' => array_map(
                fn (EstadoNoConformidad $destino): array => [
                    'valor' => $destino->value,
                    'etiqueta' => $destino->etiqueta(),
                    'tono' => $destino->tono(),
                    'icono' => $destino->icono(),
                    'exigeMotivo' => $this->exigeMotivo($no_conformidad->estado, $destino),
                    'permiso' => $destino === EstadoNoConformidad::Verificada
                        ? Permiso::NoConformidadesVerificar->value
                        : Permiso::NoConformidadesGestionar->value,
                ],
                $no_conformidad->estado->transicionesPermitidas(),
            ),
            'historial' => $no_conformidad->transiciones
                ->map(fn (NoConformidadTransicion $transicion): array => [
                    'id' => $transicion->id,
                    'anterior' => $transicion->estado_anterior?->etiqueta(),
                    'nuevo' => $transicion->estado_nuevo->etiqueta(),
                    'tono' => $transicion->estado_nuevo->tono(),
                    'icono' => $transicion->estado_nuevo->icono(),
                    'usuario' => $transicion->usuario?->name,
                    'fecha' => $transicion->created_at->format('d/m/Y H:i'),
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
            'puedeGestionar' => $this->puede(Permiso::NoConformidadesGestionar),
            'puedeVerificar' => $this->puede(Permiso::NoConformidadesVerificar),
            ...$this->opciones(),
        ]);
    }

    public function edit(NoConformidad $no_conformidad): Response
    {
        $no_conformidad->load('hallazgo.auditoria');

        return Inertia::render('no-conformidades/Formulario', [
            'noConformidad' => $this->serializar($no_conformidad),
            'hallazgo' => $no_conformidad->hallazgo === null
                ? null
                : $this->serializarHallazgo($no_conformidad->hallazgo),
            'sugerencia' => null,
            /*
             * Con una prueba de continuidad o un incidente detrás, el origen
             * no se elige: sus `CHECK` sólo admiten el suyo, y
             * `GuardarNoConformidadRequest` rechaza el cambio. El formulario
             * lo enseña como texto, igual que con un hallazgo.
             */
            'origenFijo' => $no_conformidad->prueba_continuidad_id !== null
                || $no_conformidad->incidente_id !== null,
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarNoConformidadRequest $request, NoConformidad $no_conformidad): RedirectResponse
    {
        $datos = $request->validated();

        // El hallazgo no se mueve en la edición: cambiarlo reescribiría de qué
        // auditoría salió, que es lo que el registro tiene que fijar.
        unset($datos['hallazgo_id']);

        $no_conformidad->update($datos);

        Inertia::flash('exito', 'No conformidad actualizada.');

        return to_route('no-conformidades.show', $no_conformidad);
    }

    public function destroy(NoConformidad $no_conformidad): RedirectResponse
    {
        $codigo = $no_conformidad->codigo;
        $no_conformidad->delete();

        Inertia::flash('exito', "No conformidad {$codigo} eliminada.");

        return to_route('no-conformidades.index');
    }

    // --- El ciclo de la cláusula 10.2 ---------------------------------------

    public function transicion(
        CambiarEstadoNoConformidadRequest $request,
        NoConformidad $no_conformidad,
        CambiarEstadoNoConformidad $cambiar,
    ): RedirectResponse {
        $destino = EstadoNoConformidad::from((string) $request->validated('estado'));

        /*
         * Verificar la eficacia va con su propio permiso, y se comprueba aquí y
         * no en la ruta porque la ruta es una sola: quien ejecuta el tratamiento
         * no firma que funcionó, que es la cláusula 10.2 e) entera.
         */
        if ($destino === EstadoNoConformidad::Verificada && ! $this->puede(Permiso::NoConformidadesVerificar)) {
            abort(403);
        }

        try {
            $cambiar(
                $no_conformidad,
                $destino,
                $request->user(),
                $request->string('nota')->value() ?: null,
            );
        } catch (TransicionDeNoConformidadNoPermitida $error) {
            return back()->withErrors(['estado' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'No conformidad actualizada.');

        return back();
    }

    // --- Las acciones correctivas -------------------------------------------

    public function abrirAccion(
        AbrirAccionCorrectivaRequest $request,
        NoConformidad $no_conformidad,
        AbrirAccionCorrectiva $abrir,
    ): RedirectResponse {
        $tarea = $abrir($no_conformidad, $request->validated(), $request->user());

        Inertia::flash('exito', "Acción correctiva «{$tarea->titulo}» abierta.");

        return back();
    }

    public function vincularAccion(
        VincularAccionCorrectivaRequest $request,
        NoConformidad $no_conformidad,
        VincularAccionCorrectiva $vincular,
    ): RedirectResponse {
        // Por el modelo y no por el id a pelo: así pasa por el scope de
        // organización, que es lo que impide vincular la tarea de otro cliente.
        $tarea = Tarea::query()->findOrFail($request->validated('tarea_id'));

        $vincular->vincular($no_conformidad, $tarea, $request->user());

        Inertia::flash('exito', 'Acción correctiva vinculada.');

        return back();
    }

    public function desvincularAccion(
        NoConformidad $no_conformidad,
        Tarea $tarea,
        VincularAccionCorrectiva $vincular,
    ): RedirectResponse {
        $vincular->desvincular($no_conformidad, $tarea);

        Inertia::flash('exito', 'Acción correctiva desvinculada.');

        return back();
    }

    /**
     * El hallazgo del que se abre, si se abre desde uno.
     *
     * Se resuelve por el modelo para que pase por el scope de organización: el de
     * otro cliente no existe, y la respuesta es 404 y nunca 403 —decir «existe y
     * no es tuyo» ya sería filtrar—.
     */
    private function hallazgoDe(Request $request): ?Hallazgo
    {
        $id = $request->integer('hallazgo');

        if ($id === 0) {
            return null;
        }

        return Hallazgo::query()->with(['auditoria', 'noConformidad'])->findOrFail($id);
    }

    private function exigeMotivo(EstadoNoConformidad $actual, EstadoNoConformidad $destino): bool
    {
        if ($destino === EstadoNoConformidad::Anulada || $destino === EstadoNoConformidad::Verificada) {
            return true;
        }

        return $destino === EstadoNoConformidad::EnTratamiento && $actual->esCerrada();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(NoConformidad $noConformidad): array
    {
        $plazo = Plazo::para(
            $noConformidad->fecha_prevista,
            $noConformidad->estado->esCerrada(),
            $noConformidad->fecha_cierre,
            $noConformidad->haVencido(),
            $noConformidad->estado === EstadoNoConformidad::Anulada ? 'Anulada' : 'Tratada',
        );

        return [
            'id' => $noConformidad->id,
            'codigo' => $noConformidad->codigo,
            'origen' => $noConformidad->origen->value,
            'origenEtiqueta' => $noConformidad->origen->etiqueta(),
            'hallazgo_id' => $noConformidad->hallazgo_id,
            'descripcion' => $noConformidad->descripcion,
            'correccion_inmediata' => $noConformidad->correccion_inmediata,
            'analisis_causa_raiz' => $noConformidad->analisis_causa_raiz,
            'estado' => $noConformidad->estado->value,
            'estadoEtiqueta' => $noConformidad->estado->etiqueta(),
            'estadoTono' => $noConformidad->estado->tono(),
            'estadoIcono' => $noConformidad->estado->icono(),
            'responsable_id' => $noConformidad->responsable_id,
            'responsable' => $noConformidad->responsable?->name,
            'fecha_deteccion' => $noConformidad->fecha_deteccion->toDateString(),
            'fecha_prevista' => $noConformidad->fecha_prevista?->toDateString(),
            'fechaCierre' => $noConformidad->fecha_cierre?->format('d/m/Y'),
            'fechaVerificacion' => $noConformidad->fecha_verificacion?->format('d/m/Y'),
            'verificadaPor' => $noConformidad->verificadaPor?->name,
            'resultado_verificacion' => $noConformidad->resultado_verificacion,
            'plazoEtiqueta' => $plazo->etiqueta,
            'plazoTono' => $plazo->tono,
        ];
    }

    /**
     * El incidente del que se abre, si se abre de uno.
     *
     * Por el modelo, para que pase por el scope de organización: el de otro
     * cliente no existe, y la respuesta es 404 y nunca 403.
     */
    private function incidenteDe(Request $request): ?Incidente
    {
        $id = $request->integer('incidente');

        if ($id === 0) {
            return null;
        }

        return Incidente::query()->with('noConformidad')->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarIncidente(Incidente $incidente): array
    {
        return [
            'id' => $incidente->id,
            'codigo' => $incidente->codigo,
            'titulo' => $incidente->titulo,
            'estado' => $incidente->estado->etiqueta(),
            'tono' => $incidente->estado->tono(),
            'icono' => $incidente->estado->icono(),
            'peligrosidad' => $incidente->peligrosidad->etiqueta(),
            'fecha' => $incidente->fecha_deteccion->format('d/m/Y'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarHallazgo(Hallazgo $hallazgo): array
    {
        return [
            'id' => $hallazgo->id,
            'tipo' => $hallazgo->tipo->value,
            'tipoEtiqueta' => $hallazgo->tipo->etiquetaCorta(),
            'tipoTono' => $hallazgo->tipo->tono(),
            'tipoIcono' => $hallazgo->tipo->icono(),
            'descripcion' => $hallazgo->descripcion,
            'auditoria' => $hallazgo->auditoria?->codigo,
            'auditoria_id' => $hallazgo->auditoria_id,
            'medida' => $hallazgo->punto?->implantacion?->requisito?->codigo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarAccion(Tarea $tarea): array
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
     * `PerteneceAOrganizacion` —la autenticación tiene que poder encontrar a
     * alguien antes de saber de qué organización es—, así que aquí no hay scope
     * global ni RLS que tapen el cruce.
     *
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'origenes' => array_map(
                static fn (OrigenNoConformidad $origen): array => [
                    'valor' => $origen->value,
                    'etiqueta' => $origen->etiqueta(),
                ],
                OrigenNoConformidad::disponibles(),
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
