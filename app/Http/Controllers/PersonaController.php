<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Persona\CodigoPersona;
use App\Domain\Persona\DesignarRol;
use App\Domain\Persona\Enums\RolEns;
use App\Domain\Persona\Enums\TipoPasoPersona;
use App\Domain\Persona\Excepciones\DesignacionIncompatible;
use App\Domain\Persona\Excepciones\PersonaNoDesignable;
use App\Domain\Persona\Excepciones\RolYaDesignado;
use App\Domain\Persona\GuardarPasos;
use App\Domain\Persona\Models\AcuerdoConfidencialidad;
use App\Domain\Persona\Models\DesignacionRol;
use App\Domain\Persona\Models\PasoPersona;
use App\Domain\Persona\Models\Persona;
use App\Domain\Persona\RegistroPersonas;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Requests\DesignarRolRequest;
use App\Http\Requests\GuardarAcuerdoRequest;
use App\Http\Requests\GuardarPasosRequest;
use App\Http\Requests\GuardarPersonaRequest;
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
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarPersonaRequest $request): RedirectResponse
    {
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
            'acuerdos' => $persona->acuerdos
                ->map(static fn (AcuerdoConfidencialidad $acuerdo): array => [
                    'id' => $acuerdo->id,
                    'fecha_firma' => $acuerdo->fecha_firma->format('d/m/Y'),
                    'vigente_hasta' => $acuerdo->vigente_hasta?->format('d/m/Y'),
                    'vigente' => $acuerdo->estaVigente(),
                    'nota' => $acuerdo->nota,
                    'evidencia_id' => $acuerdo->evidencia_id,
                ])
                ->values()
                ->all(),
            'pasos' => $this->pasosPorTipo($persona),
            'puedeGestionar' => $this->puede(Permiso::PersonasGestionar),
            'puedeDesignar' => $this->puede(Permiso::PersonasDesignar),
            ...$this->opciones(),
        ]);
    }

    public function edit(Persona $persona): Response
    {
        return Inertia::render('personas/Formulario', [
            'persona' => $this->serializar($persona),
            'sugerencia' => null,
            ...$this->opciones(),
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
            'puesto' => $persona->puesto,
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
                        'hechoEn' => $paso->hecho_en?->format('d/m/Y'),
                    ])
                    ->values()
                    ->all(),
            ],
            TipoPasoPersona::cases(),
        );
    }

    /**
     * Las opciones de los desplegables.
     *
     * Los usuarios van acotados a la organización a mano: `User` no lleva
     * `PerteneceAOrganizacion`, así que aquí no hay scope global ni RLS que tapen
     * el cruce.
     *
     * **Las acciones formativas no entran aquí**: la asistencia se registra desde
     * la sesión y no desde la persona, porque marcar veinte asistencias de una
     * sesión es un gesto y apuntar veinte sesiones de una persona no lo es.
     *
     * @return array<string, mixed>
     */
    private function opciones(): array
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
