<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Incidente\Models\Incidente;
use App\Domain\Mejora\AbrirActuacionDeMejora;
use App\Domain\Mejora\CambiarEstadoMejora;
use App\Domain\Mejora\CodigoMejora;
use App\Domain\Mejora\Enums\EstadoMejora;
use App\Domain\Mejora\Enums\OrigenMejora;
use App\Domain\Mejora\Excepciones\TransicionDeMejoraNoPermitida;
use App\Domain\Mejora\Models\Mejora;
use App\Domain\Mejora\Models\MejoraTransicion;
use App\Domain\Mejora\RegistrarMejora;
use App\Domain\Mejora\RegistroMejoras;
use App\Domain\Mejora\VincularActuacionDeMejora;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Tarea\Coste;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\Plazo;
use App\Http\Requests\AbrirActuacionDeMejoraRequest;
use App\Http\Requests\CambiarEstadoMejoraRequest;
use App\Http\Requests\GuardarMejoraRequest;
use App\Http\Requests\VincularActuacionDeMejoraRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\MejoraRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El registro de oportunidades de mejora: la cláusula 10.1.
 *
 * Es la última entrada que le faltaba a la revisión por la dirección, y el sitio
 * donde vive lo que se puede hacer mejor **sin que nada incumpla**. Hasta aquí una
 * oportunidad de mejora sólo existía dentro de una auditoría, como
 * `TipoHallazgo::OportunidadMejora`.
 *
 * **Dos permisos y no tres**, y es lo que lo separa del registro de no
 * conformidades: aquí no hay nada que firmar. No hay eficacia que verificar porque
 * no había nada roto, y no hay compromiso que aprobar porque nadie se obligó — una
 * mejora que se convierte en compromiso es un objetivo de la 6.2.
 *
 * Una mejora se abre **desde un hallazgo** —el camino que la deja trazable— o
 * suelta, que es el caso normal: la mayoría se le ocurren a alguien un martes.
 */
class MejoraController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, MejoraRecurso $recurso, RegistroMejoras $registro): Response
    {
        return Inertia::render('mejoras/Index', [
            ...$this->tabla($recurso, $request),
            /*
             * **Sin `alertas`, y es el único registro del producto que va así.**
             * Ninguna cifra de aquí va mal de verdad: una idea sin hacer no
             * incumple nada. Ver `RegistroMejoras`.
             */
            'pendientes' => $registro->pendientes(),
            'total' => $registro->total(),
        ]);
    }

    public function create(Request $request, CodigoMejora $codigos): Response|RedirectResponse
    {
        $hallazgo = $this->hallazgoDe($request);
        $incidente = $this->incidenteDe($request);

        // Un hallazgo se trata una vez y el índice único lo impone: es más barato
        // llevar a la que ya existe que dejar rellenar el formulario entero.
        if ($hallazgo?->mejora !== null) {
            Inertia::flash('aviso', 'Ese hallazgo ya tiene su oportunidad de mejora.');

            return to_route('mejoras.show', $hallazgo->mejora);
        }

        // El espejo de la puerta del otro registro: una no conformidad no se
        // trata aquí.
        if ($hallazgo !== null && ! $hallazgo->tipo->abreMejora()) {
            Inertia::flash('aviso', 'Ese hallazgo se trata como no conformidad (cláusula 10.2).');

            return redirect()->to("/no-conformidades/crear?hallazgo={$hallazgo->id}");
        }

        return Inertia::render('mejoras/Formulario', [
            'mejora' => null,
            'hallazgo' => $hallazgo === null ? null : $this->serializarHallazgo($hallazgo),
            'sugerencia' => [
                'codigo' => $codigos->siguiente($hallazgo?->auditoria->fecha),
                /*
                 * **Desde un incidente sólo se pone el origen, sin clave
                 * foránea**, a diferencia de lo que pasa con un hallazgo. Es el
                 * mismo reparto que `OrigenMejora::RevisionDireccion`: la mejora
                 * que sale de una lección aprendida no «trata» el incidente
                 * —ése ya está cerrado—, así que atarla sería fingir una
                 * trazabilidad que no hay. Lo que sí se hereda es el título, para
                 * que nadie reescriba el mismo hecho dos veces.
                 */
                'origen' => match (true) {
                    $hallazgo !== null => OrigenMejora::Auditoria->value,
                    $incidente !== null => OrigenMejora::Incidente->value,
                    default => OrigenMejora::Propia->value,
                },
                'titulo' => match (true) {
                    $hallazgo !== null => $hallazgo->descripcion,
                    $incidente !== null => "Lección aprendida de {$incidente->codigo}: {$incidente->titulo}",
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

    public function store(GuardarMejoraRequest $request, RegistrarMejora $registrar): RedirectResponse
    {
        $datos = $request->validated();

        // Por el modelo y no por el id a pelo: así pasa por el scope de
        // organización, que es lo que impide colgarla de un hallazgo ajeno.
        if (($datos['hallazgo_id'] ?? null) !== null) {
            $datos['hallazgo_id'] = Hallazgo::query()->findOrFail($datos['hallazgo_id'])->id;
        }

        $mejora = $registrar($datos, $request->user());

        Inertia::flash('exito', "Oportunidad de mejora {$mejora->codigo} registrada.");

        return to_route('mejoras.show', $mejora);
    }

    public function show(Mejora $mejora): Response
    {
        $mejora->load([
            'responsable',
            'hallazgo.auditoria',
            'hallazgo.punto.implantacion.requisito',
            'tareas.responsable',
            'transiciones.usuario',
        ]);

        return Inertia::render('mejoras/Ficha', [
            'mejora' => $this->serializar($mejora),
            'hallazgo' => $mejora->hallazgo === null
                ? null
                : $this->serializarHallazgo($mejora->hallazgo),
            'actuaciones' => $mejora->tareas
                ->map(fn (Tarea $tarea): array => $this->serializarActuacion($tarea))
                ->values()
                ->all(),
            /*
             * El coste se cuenta **sobre tareas distintas**, como en el plan de
             * adecuación y en los dos registros hermanos. Y no entra en el
             * presupuesto de ese plan: una mejora no es una brecha del Anexo II.
             */
            'coste' => [
                'total' => Coste::escribir(Coste::total($mejora->tareas->all())),
                'sinEstimar' => Coste::sinEstimar($mejora->tareas->all()),
            ],
            'transiciones' => array_map(
                fn (EstadoMejora $destino): array => [
                    'valor' => $destino->value,
                    'etiqueta' => $destino->etiqueta(),
                    'tono' => $destino->tono(),
                    'icono' => $destino->icono(),
                    'exigeMotivo' => $destino === EstadoMejora::Descartada,
                    'permiso' => Permiso::MejorasGestionar->value,
                ],
                $mejora->estado->transicionesPermitidas(),
            ),
            'historial' => $mejora->transiciones
                ->map(fn (MejoraTransicion $transicion): array => [
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
            'puedeGestionar' => $this->puede(Permiso::MejorasGestionar),
            ...$this->opciones(),
        ]);
    }

    public function edit(Mejora $mejora): Response
    {
        $mejora->load('hallazgo.auditoria');

        return Inertia::render('mejoras/Formulario', [
            'mejora' => $this->serializar($mejora),
            'hallazgo' => $mejora->hallazgo === null
                ? null
                : $this->serializarHallazgo($mejora->hallazgo),
            'sugerencia' => null,
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarMejoraRequest $request, Mejora $mejora): RedirectResponse
    {
        $datos = $request->validated();

        // El hallazgo no se mueve en la edición: cambiarlo reescribiría de qué
        // auditoría salió.
        unset($datos['hallazgo_id']);

        $mejora->update($datos);

        Inertia::flash('exito', 'Oportunidad de mejora actualizada.');

        return to_route('mejoras.show', $mejora);
    }

    public function destroy(Mejora $mejora): RedirectResponse
    {
        $codigo = $mejora->codigo;
        $mejora->delete();

        Inertia::flash('exito', "Oportunidad de mejora {$codigo} eliminada.");

        return to_route('mejoras.index');
    }

    // --- El ciclo de la cláusula 10.1 ---------------------------------------

    public function transicion(
        CambiarEstadoMejoraRequest $request,
        Mejora $mejora,
        CambiarEstadoMejora $cambiar,
    ): RedirectResponse {
        try {
            $cambiar(
                $mejora,
                EstadoMejora::from((string) $request->validated('estado')),
                $request->user(),
                $request->string('nota')->value() ?: null,
            );
        } catch (TransicionDeMejoraNoPermitida $error) {
            return back()->withErrors(['estado' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Oportunidad de mejora actualizada.');

        return back();
    }

    // --- Lo que se va a hacer -----------------------------------------------

    public function abrirActuacion(
        AbrirActuacionDeMejoraRequest $request,
        Mejora $mejora,
        AbrirActuacionDeMejora $abrir,
    ): RedirectResponse {
        $tarea = $abrir($mejora, $request->validated(), $request->user());

        Inertia::flash('exito', "Actuación «{$tarea->titulo}» abierta.");

        return back();
    }

    public function vincularActuacion(
        VincularActuacionDeMejoraRequest $request,
        Mejora $mejora,
        VincularActuacionDeMejora $vincular,
    ): RedirectResponse {
        $tarea = Tarea::query()->findOrFail($request->validated('tarea_id'));

        $vincular->vincular($mejora, $tarea, $request->user());

        Inertia::flash('exito', 'Actuación vinculada.');

        return back();
    }

    public function desvincularActuacion(
        Mejora $mejora,
        Tarea $tarea,
        VincularActuacionDeMejora $vincular,
    ): RedirectResponse {
        $vincular->desvincular($mejora, $tarea);

        Inertia::flash('exito', 'Actuación desvinculada. La tarea sigue en el plan de acción.');

        return back();
    }

    /**
     * El hallazgo del que se abre, si se abre desde uno.
     *
     * Se resuelve por el modelo para que pase por el scope de organización: el de
     * otro cliente no existe, y la respuesta es 404 y nunca 403.
     */
    private function hallazgoDe(Request $request): ?Hallazgo
    {
        $id = $request->integer('hallazgo');

        if ($id === 0) {
            return null;
        }

        return Hallazgo::query()->with(['auditoria', 'mejora'])->findOrFail($id);
    }

    /**
     * El incidente del que se abre, si se abre de uno.
     *
     * Sólo para heredar el origen y el título: `mejoras` **no tiene
     * `incidente_id`**, y es deliberado —ver la nota del `sugerencia`—. Se
     * resuelve por el modelo igual, para que el de otro cliente responda 404.
     */
    private function incidenteDe(Request $request): ?Incidente
    {
        $id = $request->integer('incidente');

        return $id === 0 ? null : Incidente::query()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Mejora $mejora): array
    {
        $plazo = Plazo::para(
            $mejora->fecha_prevista,
            $mejora->estado->esCerrada(),
            $mejora->fecha_cierre,
            $mejora->sePasoDeFecha(),
            $mejora->estado === EstadoMejora::Descartada ? 'Descartada' : 'Implantada',
        );

        return [
            'id' => $mejora->id,
            'codigo' => $mejora->codigo,
            'origen' => $mejora->origen->value,
            'origenEtiqueta' => $mejora->origen->etiqueta(),
            'hallazgo_id' => $mejora->hallazgo_id,
            'titulo' => $mejora->titulo,
            'descripcion' => $mejora->descripcion,
            'beneficio_esperado' => $mejora->beneficio_esperado,
            'estado' => $mejora->estado->value,
            'estadoEtiqueta' => $mejora->estado->etiqueta(),
            'estadoTono' => $mejora->estado->tono(),
            'estadoIcono' => $mejora->estado->icono(),
            'responsable_id' => $mejora->responsable_id,
            'responsable' => $mejora->responsable?->name,
            'fecha_deteccion' => $mejora->fecha_deteccion->toDateString(),
            'fecha_prevista' => $mejora->fecha_prevista?->toDateString(),
            'fechaCierre' => $mejora->fecha_cierre?->format('d/m/Y'),
            'previstaEtiqueta' => $plazo->etiqueta,
            // El rojo se rebaja a gris: aquí no hay plazo que incumplir.
            'previstaTono' => $plazo->tono === 'caducada' ? 'no_iniciado' : $plazo->tono,
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
     * `PerteneceAOrganizacion`, así que aquí no hay scope global ni RLS que tapen
     * el cruce.
     *
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'origenes' => array_map(
                static fn (OrigenMejora $origen): array => [
                    'valor' => $origen->value,
                    'etiqueta' => $origen->etiqueta(),
                ],
                OrigenMejora::disponibles(),
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
