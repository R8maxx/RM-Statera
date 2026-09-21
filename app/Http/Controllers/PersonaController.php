<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Adjunto\BorrarAdjunto;
use App\Domain\Adjunto\Models\Adjunto;
use App\Domain\Adjunto\SubirAdjunto;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Persona\AsignarPuesto;
use App\Domain\Persona\CodigoPersona;
use App\Domain\Persona\DesignarRol;
use App\Domain\Persona\Enums\RolEns;
use App\Domain\Persona\Enums\TipoPasoPersona;
use App\Domain\Persona\Excepciones\DesignacionIncompatible;
use App\Domain\Persona\Excepciones\PersonaNoDesignable;
use App\Domain\Persona\Excepciones\RolYaDesignado;
use App\Domain\Persona\GuardarPasos;
use App\Domain\Persona\Models\AcuerdoConfidencialidad;
use App\Domain\Persona\Models\AsignacionPuesto;
use App\Domain\Persona\Models\DesignacionRol;
use App\Domain\Persona\Models\PasoPersona;
use App\Domain\Persona\Models\Persona;
use App\Domain\Persona\Models\Puesto;
use App\Domain\Persona\RegistroPersonas;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Controllers\Concerns\GestionaAdjuntos;
use App\Http\Requests\AsignarPuestoRequest;
use App\Http\Requests\DesignarRolRequest;
use App\Http\Requests\GuardarAcuerdoRequest;
use App\Http\Requests\GuardarPasosRequest;
use App\Http\Requests\GuardarPersonaRequest;
use App\Http\Requests\SubirAdjuntoRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\PersonaRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El registro de personas: § 4.8, cláusula 5.3 y `mp.per.*`.
 *
 * **No sustituye a `users`.** Los responsables de activos, tareas y evidencias
 * siguen siendo cuentas de Statera; esto es la plantilla, y la mayoría de ella no
 * entra nunca en la herramienta. `personas.user_id` es el puente entre los dos.
 *
 * **Tres permisos, y el tercero es de supervisión.** Dar de alta a alguien,
 * apuntar su formación y marcar su checklist es trabajo del técnico; **designar al
 * responsable de seguridad de un sistema es un nombramiento** que la organización
 * firma y que el auditor pide por escrito. Es la misma familia que
 * `sistemas.valorar`, `riesgos.aceptar` y `objetivos.aprobar`.
 */
class PersonaController extends Controller
{
    use GestionaAdjuntos;
    use RespondeConRecurso;

    public function index(Request $request, PersonaRecurso $recurso, RegistroPersonas $registro): Response
    {
        return Inertia::render('personas/Index', [
            ...$this->tabla($recurso, $request),
            'alertas' => $registro->alertas(),
            'pendientes' => $registro->pendientes(),
            'cobertura' => $registro->cobertura(),
            'total' => $registro->total(),
        ]);
    }

    public function create(CodigoPersona $codigos): Response
    {
        return Inertia::render('personas/Formulario', [
            'persona' => null,
            'sugerencia' => [
                'codigo' => $codigos->siguiente(),
                'fecha_alta' => now()->toDateString(),
            ],
            ...$this->opcionesDeCuenta(),
        ]);
    }

    public function store(GuardarPersonaRequest $request): RedirectResponse
    {
        // `nombre` llega relleno porque `Persona` relee la fila al crearse: es
        // columna generada y el `INSERT` no la devuelve. Ver el modelo.
        $persona = Persona::query()->create($request->validated());

        Inertia::flash('exito', "{$persona->nombre} está en el registro de personas.");

        return to_route('personas.show', $persona);
    }

    public function show(Persona $persona): Response
    {
        $persona->load([
            'usuario',
            'designaciones.sistema',
            'designaciones.designadaPor',
            'acuerdos.evidencia',
            'pasos',
            'asignaciones.puesto',
            'asignaciones.asignadaPor',
            'adjuntos.subidoPor',
        ]);

        $formacion = $persona->asistencias()
            ->with('accionFormativa')
            ->get()
            ->sortByDesc(fn ($asistencia) => $asistencia->accionFormativa?->fecha)
            ->values();

        return Inertia::render('personas/Ficha', [
            'persona' => $this->serializar($persona),
            'designaciones' => $persona->designaciones
                ->map(fn (DesignacionRol $designacion): array => [
                    'id' => $designacion->id,
                    'rol' => $designacion->rol->value,
                    'rolEtiqueta' => $designacion->rol->etiqueta(),
                    'rolTono' => $designacion->rol->tono(),
                    'rolIcono' => $designacion->rol->icono(),
                    'sistema_id' => $designacion->sistema_id,
                    'sistema' => $designacion->sistema?->codigo,
                    'sistemaNombre' => $designacion->sistema?->nombre,
                    'desde' => $designacion->desde->format('d/m/Y'),
                    'hasta' => $designacion->hasta?->format('d/m/Y'),
                    'vigente' => $designacion->estaVigente(),
                    'designadaPor' => $designacion->designadaPor?->name,
                    'nota' => $designacion->nota,
                ])
                ->values()
                ->all(),
            'formacion' => $formacion
                ->map(static fn ($asistencia): array => [
                    'id' => $asistencia->id,
                    'accion_formativa_id' => $asistencia->accion_formativa_id,
                    'codigo' => $asistencia->accionFormativa?->codigo,
                    'titulo' => $asistencia->accionFormativa?->titulo,
                    'tipo' => $asistencia->accionFormativa?->tipo->etiqueta(),
                    'tipoTono' => $asistencia->accionFormativa?->tipo->tono(),
                    'tipoIcono' => $asistencia->accionFormativa?->tipo->icono(),
                    'medida' => $asistencia->accionFormativa?->tipo->medida(),
                    'fecha' => $asistencia->accionFormativa?->fecha->format('d/m/Y'),
                    'asistio' => $asistencia->asistio,
                ])
                ->all(),
            /*
             * El histórico de puestos. Va con la ficha y no por `Inertia::optional`
             * porque son pocas filas y es de lo primero que se mira.
             */
            'asignaciones' => $persona->asignaciones
                ->sortByDesc('desde')
                ->map(static fn (AsignacionPuesto $asignacion): array => [
                    'id' => $asignacion->id,
                    'puesto_id' => $asignacion->puesto_id,
                    'puesto' => $asignacion->puesto?->titulo,
                    'codigo' => $asignacion->puesto?->codigo,
                    'desde' => $asignacion->desde->format('d/m/Y'),
                    'hasta' => $asignacion->hasta?->format('d/m/Y'),
                    'vigente' => $asignacion->estaVigente(),
                    'asignadaPor' => $asignacion->asignadaPor?->name,
                    'nota' => $asignacion->nota,
                ])
                ->values()
                ->all(),
            'puestos' => Puesto::query()
                ->orderBy('titulo')
                ->get()
                ->map(static fn (Puesto $puesto): array => [
                    'valor' => (string) $puesto->id,
                    'etiqueta' => $puesto->titulo,
                ])
                ->values()
                ->all(),
            'adjuntos' => $this->serializarAdjuntos($persona, request(), "/personas/{$persona->id}/adjuntos"),
            'acuerdos' => $persona->acuerdos
                ->map(static fn (AcuerdoConfidencialidad $acuerdo): array => [
                    'id' => $acuerdo->id,
                    'fecha_firma' => $acuerdo->fecha_firma->format('d/m/Y'),
                    'vigente_hasta' => $acuerdo->vigente_hasta?->format('d/m/Y'),
                    'vigente' => $acuerdo->estaVigente(),
                    'nota' => $acuerdo->nota,
                    'evidencia_id' => $acuerdo->evidencia_id,
                    'evidencia' => $acuerdo->evidencia?->titulo,
                ])
                ->values()
                ->all(),
            'pasos' => $this->pasosPorTipo($persona),
            'maximoPasos' => GuardarPasos::TOPE,
            /*
             * El documento firmado de `mp.per.2`.
             *
             * No viaja con la ficha: el repositorio puede tener cientos de
             * evidencias y aquí se enseñan al abrir un diálogo. Mismo patrón
             * que el bloque de evidencias de una implantación.
             */
            'evidenciasDisponibles' => Inertia::optional(fn (): array => Evidencia::query()
                ->orderByDesc('fecha_obtencion')
                ->limit(100)
                ->get()
                ->map(static fn (Evidencia $evidencia): array => [
                    'valor' => (string) $evidencia->id,
                    'etiqueta' => $evidencia->titulo,
                ])
                ->all()),
            'puedeGestionar' => $this->puede(Permiso::PersonasGestionar),
            'puedeDesignar' => $this->puede(Permiso::PersonasDesignar),
            ...$this->opcionesDeNombramiento(),
        ]);
    }

    public function edit(Persona $persona): Response
    {
        return Inertia::render('personas/Formulario', [
            'persona' => $this->serializar($persona),
            'sugerencia' => null,
            ...$this->opcionesDeCuenta(),
        ]);
    }

    public function update(GuardarPersonaRequest $request, Persona $persona): RedirectResponse
    {
        $persona->update($request->validated());

        Inertia::flash('exito', 'Persona actualizada.');

        return to_route('personas.show', $persona);
    }

    public function destroy(Persona $persona): RedirectResponse
    {
        $nombre = $persona->nombre;
        $persona->delete();

        Inertia::flash('exito', "{$nombre} se ha eliminado del registro.");

        return to_route('personas.index');
    }

    // --- La cláusula 5.3 -----------------------------------------------------

    public function designar(
        DesignarRolRequest $request,
        Persona $persona,
        DesignarRol $designar,
    ): RedirectResponse {
        // Por el modelo y no por el id a pelo: así pasa por el scope de
        // organización, y el sistema de otro cliente responde 404.
        $sistema = Sistema::query()->findOrFail($request->validated('sistema_id'));
        $desde = $request->date('desde');

        try {
            $designacion = $designar(
                $persona,
                $sistema,
                RolEns::from((string) $request->validated('rol')),
                $desde,
                $request->user(),
                $request->string('nota')->value() ?: null,
            );
        } catch (DesignacionIncompatible|PersonaNoDesignable|RolYaDesignado $error) {
            return back()->withErrors(['rol' => $error->getMessage()]);
        }

        Inertia::flash('exito', "{$persona->nombre} designada como {$designacion->rol->etiqueta()} de {$sistema->codigo}.");

        return back();
    }

    /**
     * Revocar un nombramiento: le pone fecha de fin.
     *
     * **No lo borra**, y por eso esto no es un `destroy`: la pregunta del auditor
     * es «¿desde cuándo?» y también «¿hasta cuándo?».
     */
    public function revocar(
        Persona $persona,
        DesignacionRol $designacion,
        DesignarRol $designar,
    ): RedirectResponse {
        $designar->revocar($designacion);

        Inertia::flash('exito', "Nombramiento de {$designacion->rol->etiqueta()} revocado el ".$designacion->hasta?->format('d/m/Y').'.');

        return back();
    }

    // --- Los deberes por escrito: mp.per.2 -----------------------------------

    /**
     * Asigna un puesto a la persona, cerrando el que tuviera.
     *
     * Quién ocupa qué se gestiona **desde la ficha de la persona** y no desde la
     * del puesto, que es donde se mira: la pregunta es «¿qué hace esta persona?»
     * mucho más a menudo que «¿quién ocupa este puesto?». La ficha del puesto lo
     * enseña, pero no lo edita.
     */
    public function asignarPuesto(AsignarPuestoRequest $request, Persona $persona, AsignarPuesto $asignar): RedirectResponse
    {
        $puesto = Puesto::query()->findOrFail($request->integer('puesto_id'));

        try {
            $asignar(
                $persona,
                $puesto,
                $request->date('desde'),
                $request->user(),
                $request->string('nota')->value() ?: null,
            );
        } catch (PersonaNoDesignable $error) {
            return back()->withErrors(['puesto_id' => $error->getMessage()]);
        }

        Inertia::flash('exito', "{$persona->nombre} ocupa «{$puesto->titulo}».");

        return to_route('personas.show', $persona);
    }

    /**
     * Cierra la asignación vigente sin poner otra.
     *
     * **No la borra**: la pregunta del auditor es «¿desde cuándo?», y también
     * «¿hasta cuándo?». Es el mismo criterio que revocar un nombramiento.
     */
    public function cerrarPuesto(Persona $persona, AsignacionPuesto $asignacion, AsignarPuesto $asignar): RedirectResponse
    {
        $asignar->cerrar($asignacion);

        Inertia::flash('exito', 'Asignación cerrada.');

        return to_route('personas.show', $persona);
    }

    public function subirAdjunto(SubirAdjuntoRequest $request, Persona $persona, SubirAdjunto $subir): RedirectResponse
    {
        return $this->subirAdjuntoDe($request, $persona, $subir, 'personas.show');
    }

    public function descargarAdjunto(Persona $persona, Adjunto $adjunto): RedirectResponse
    {
        return $this->descargarAdjuntoDe($adjunto);
    }

    public function borrarAdjunto(Persona $persona, Adjunto $adjunto, BorrarAdjunto $borrar): RedirectResponse
    {
        return $this->borrarAdjuntoDe($adjunto, $borrar, 'personas.show', $persona);
    }

    public function guardarAcuerdo(GuardarAcuerdoRequest $request, Persona $persona): RedirectResponse
    {
        $persona->acuerdos()->create([
            'organizacion_id' => $persona->organizacion_id,
            ...$request->validated(),
        ]);

        Inertia::flash('exito', 'Acuerdo de confidencialidad registrado.');

        return back();
    }

    public function borrarAcuerdo(Persona $persona, AcuerdoConfidencialidad $acuerdo): RedirectResponse
    {
        $acuerdo->delete();

        Inertia::flash('exito', 'Acuerdo eliminado.');

        return back();
    }

    // --- Las dos checklists --------------------------------------------------

    public function guardarPasos(
        GuardarPasosRequest $request,
        Persona $persona,
        GuardarPasos $guardar,
    ): RedirectResponse {
        /** @var list<array{id?: int|string|null, titulo?: string|null, hecho?: bool|null}> $pasos */
        $pasos = $request->validated('pasos', []);

        $guardar($persona, TipoPasoPersona::from((string) $request->validated('tipo')), $pasos);

        Inertia::flash('exito', 'Checklist guardada.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Persona $persona): array
    {
        return [
            'id' => $persona->id,
            'codigo' => $persona->codigo,
            'nombre' => $persona->nombre,
            'nombre_pila' => $persona->nombre_pila,
            'apellido1' => $persona->apellido1,
            'apellido2' => $persona->apellido2,
            'nif' => $persona->nif,
            'telefono' => $persona->telefono,
            'telefono_fijo' => $persona->telefono_fijo,
            'direccion' => $persona->direccion,
            'fecha_nacimiento' => $persona->fecha_nacimiento?->toDateString(),
            // Ya no es una columna: es la asignación vigente.
            'puesto' => $persona->puestoVigente()?->titulo,
            'puesto_id' => $persona->puestoVigente()?->id,
            'email' => $persona->email,
            'user_id' => $persona->user_id,
            'usuario' => $persona->usuario?->name,
            'fecha_alta' => $persona->fecha_alta->toDateString(),
            'fecha_baja' => $persona->fecha_baja?->toDateString(),
            'notas' => $persona->notas,
            'activa' => $persona->estaActiva(),
            'estadoEtiqueta' => $persona->estaActiva() ? 'En plantilla' : 'Dada de baja',
            'estadoTono' => $persona->estaActiva() ? 'implantado' : 'no_iniciado',
            'estadoIcono' => $persona->estaActiva() ? 'UserCheck' : 'Archive',
            /*
             * El único rojo del módulo, y es el hermano exacto del equipo retirado
             * sin constancia de borrado: la herramienta no corrige el dato, lo
             * pone delante.
             */
            'esperaCierreDeBaja' => $persona->esperaCierreDeBaja(),
        ];
    }

    /**
     * Las dos checklists, cada una con su tipo y sus pasos.
     *
     * **Las dos salen siempre**, aunque estén vacías: una checklist de salida sin
     * abrir es exactamente lo que hay que poder abrir el día que alguien se va.
     *
     * @return list<array<string, mixed>>
     */
    private function pasosPorTipo(Persona $persona): array
    {
        return array_map(
            fn (TipoPasoPersona $tipo): array => [
                'tipo' => $tipo->value,
                'etiqueta' => $tipo->etiqueta(),
                'icono' => $tipo->icono(),
                'tono' => $tipo->tono(),
                'pasos' => $persona->pasos
                    ->where('tipo', $tipo)
                    ->map(static fn (PasoPersona $paso): array => [
                        'id' => $paso->id,
                        'titulo' => $paso->titulo,
                        'hecho' => $paso->hecho_en !== null,
                        'hechoEn' => $paso->hecho_en?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ],
            TipoPasoPersona::cases(),
        );
    }

    /**
     * Las opciones del **nombramiento**, que vive en la ficha.
     *
     * Estaban las tres listas juntas y viajaban a las tres pantallas: el
     * formulario recibía roles y sistemas que no usa —los declaraba como
     * `unknown` con un comentario— y la ficha recibía las cuentas, que tampoco.
     * Tres consultas de más por pantalla y tres props muertas.
     *
     * **Las acciones formativas no entran aquí**: la asistencia se registra desde
     * la sesión y no desde la persona, porque marcar veinte asistencias de una
     * sesión es un gesto y apuntar veinte sesiones de una persona no lo es.
     *
     * @return array<string, mixed>
     */
    private function opcionesDeNombramiento(): array
    {
        return [
            'roles' => array_map(
                static fn (RolEns $rol): array => [
                    'valor' => $rol->value,
                    'etiqueta' => $rol->etiqueta(),
                    'unico' => $rol->esUnicoPorSistema(),
                    /*
                     * Los valores y no las etiquetas: el cliente los cruza con
                     * los nombramientos vigentes para avisar **antes** de enviar,
                     * y comparar cadenas traducidas es cómo se rompe ese aviso el
                     * día que alguien retoca una etiqueta.
                     */
                    'incompatibles' => array_map(
                        static fn (RolEns $otro): string => $otro->value,
                        $rol->incompatibleCon(),
                    ),
                ],
                RolEns::cases(),
            ),
            'sistemas' => Sistema::query()
                ->orderBy('codigo')
                ->get()
                ->map(static fn (Sistema $sistema): array => [
                    'valor' => (string) $sistema->id,
                    'etiqueta' => "{$sistema->codigo} · {$sistema->nombre}",
                ])
                ->all(),
        ];
    }

    /**
     * Las cuentas de Statera a las que se puede enlazar una persona.
     *
     * Acotadas a la organización **a mano**: `User` no lleva
     * `PerteneceAOrganizacion`, así que aquí no hay scope global ni RLS que
     * tapen el cruce, y ningún test de aislamiento lo cazaría.
     *
     * @return array<string, mixed>
     */
    private function opcionesDeCuenta(): array
    {
        return [
            'cuentas' => User::query()
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
