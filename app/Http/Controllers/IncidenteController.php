<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Incidente\CambiarEstadoIncidente;
use App\Domain\Incidente\CodigoIncidente;
use App\Domain\Incidente\Enums\ClasificacionIncidente;
use App\Domain\Incidente\Enums\EstadoIncidente;
use App\Domain\Incidente\Enums\PeligrosidadIncidente;
use App\Domain\Incidente\Excepciones\TransicionDeIncidenteNoPermitida;
use App\Domain\Incidente\Models\Incidente;
use App\Domain\Incidente\Models\IncidenteTransicion;
use App\Domain\Incidente\RegistrarIncidente;
use App\Domain\Incidente\RegistrarNotificacion;
use App\Domain\Incidente\RegistroIncidentes;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Requests\CambiarEstadoIncidenteRequest;
use App\Http\Requests\GuardarIncidenteRequest;
use App\Http\Requests\GuardarLeccionRequest;
use App\Http\Requests\RegistrarNotificacionRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\IncidenteRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El registro de incidentes: § 4.10 y `op.exp.7`.
 *
 * **Dos permisos y ninguno de supervisión.** Notificar a un supervisor no es una
 * decisión que se delibere: es una obligación con reloj, y ponerle un permiso
 * aparte metería un paso entre el reloj y la notificación. Lo que sí exige firma
 * es la no conformidad que salga del incidente, y ésa ya tiene la suya.
 *
 * **Y las notificaciones tienen ruta propia**, cada una con su fecha: anotar que
 * se notificó a la AEPD es el dato que el auditor contrasta contra el
 * justificante, y mezclarlo con los otros veinte campos del formulario haría que
 * se rellenara de pasada.
 */
class IncidenteController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, IncidenteRecurso $recurso, RegistroIncidentes $registro): Response
    {
        return Inertia::render('incidentes/Index', [
            ...$this->tabla($recurso, $request),
            'alertas' => $registro->alertas(),
            'pendientes' => $registro->pendientes(),
            'total' => $registro->total(),
        ]);
    }

    public function create(CodigoIncidente $codigos): Response
    {
        return Inertia::render('incidentes/Formulario', [
            'incidente' => null,
            'activosVinculados' => [],
            'sugerencia' => [
                'codigo' => $codigos->siguiente(),
                'fecha_deteccion' => now()->format('Y-m-d\TH:i'),
            ],
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarIncidenteRequest $request, RegistrarIncidente $registrar): RedirectResponse
    {
        $datos = $request->validated();
        $activos = $datos['activos'] ?? [];
        unset($datos['activos']);

        $incidente = $registrar($datos, $request->user());

        $this->sincronizarActivos($incidente, $activos);

        Inertia::flash('exito', "Incidente {$incidente->codigo} registrado.");

        return to_route('incidentes.show', $incidente);
    }

    public function show(Incidente $incidente): Response
    {
        $incidente->load([
            'responsable',
            'sistema',
            'activos',
            'noConformidad',
            'transiciones.usuario',
        ]);

        $aepd = $incidente->plazoAepd();
        $ccn = $incidente->notificacionCcnCert();

        return Inertia::render('incidentes/Ficha', [
            'incidente' => $this->serializar($incidente),
            'activos' => $incidente->activos
                ->map(static fn (Activo $activo): array => [
                    'id' => $activo->id,
                    'codigo' => $activo->codigo,
                    'nombre' => $activo->nombre,
                    'tipo' => $activo->tipo->etiqueta(),
                    // La familia propia de los tipos de activo, `--tipo-*`: un
                    // tipo dice *qué es* algo y un estado *cómo va*, y con la
                    // misma saturación un badge de tipo en verde se leería como
                    // «implantado».
                    'tipoTono' => 'tipo:'.$activo->tipo->value,
                    'tipoIcono' => $activo->tipo->icono(),
                ])
                ->values()
                ->all(),
            'notificaciones' => [
                'aepd' => [
                    'destinatario' => 'aepd',
                    'nombre' => 'AEPD',
                    'notificable' => $incidente->notificable_aepd,
                    'notificado' => $aepd->notificado,
                    'notificadoEn' => $incidente->notificado_aepd_en?->format('d/m/Y H:i'),
                    'vencido' => $aepd->vencido,
                    'horasRestantes' => $aepd->horasRestantes,
                    'estado' => $aepd->estado,
                    'etiqueta' => $aepd->etiqueta,
                    'tono' => $aepd->tono,
                    'icono' => $aepd->icono,
                    // El número sale del RGPD y va citado: un plazo sin su fuente
                    // es una opinión.
                    'fundamento' => 'Artículo 33.1 del RGPD: 72 horas desde que se tiene constancia.',
                ],
                'ccnCert' => [
                    'destinatario' => 'ccn_cert',
                    'nombre' => 'CCN-CERT',
                    'notificable' => $incidente->notificable_ccn_cert,
                    'notificado' => $ccn->notificado,
                    'notificadoEn' => $incidente->notificado_ccn_cert_en?->format('d/m/Y H:i'),
                    'vencido' => false,
                    'horasRestantes' => null,
                    'estado' => $ccn->estado,
                    'etiqueta' => $ccn->etiqueta,
                    'tono' => $ccn->tono,
                    'icono' => $ccn->icono,
                    /*
                     * **Sin cuenta atrás, y se dice por escrito.** El RD 311/2022
                     * no fija horas: dice «sin dilación». Poner un número sería
                     * una opinión de la herramienta disfrazada de plazo legal.
                     */
                    'fundamento' => 'El RD 311/2022 no fija un plazo en horas: exige notificar «sin dilación». Statera no inventa una cuenta atrás.',
                ],
            ],
            'noConformidad' => $incidente->noConformidad === null ? null : [
                'id' => $incidente->noConformidad->id,
                'codigo' => $incidente->noConformidad->codigo,
                'estado' => $incidente->noConformidad->estado->etiqueta(),
                'tono' => $incidente->noConformidad->estado->tono(),
                'icono' => $incidente->noConformidad->estado->icono(),
            ],
            'transiciones' => array_map(
                fn (EstadoIncidente $destino): array => [
                    'valor' => $destino->value,
                    'etiqueta' => $destino->etiqueta(),
                    'tono' => $destino->tono(),
                    'icono' => $destino->icono(),
                    'exigeMotivo' => $this->retrocede($incidente->estado, $destino),
                    'exigeLeccion' => $destino->esCerrado(),
                    'permiso' => Permiso::IncidentesGestionar->value,
                ],
                $incidente->estado->transicionesPermitidas(),
            ),
            'historial' => $incidente->transiciones
                ->map(static fn (IncidenteTransicion $transicion): array => [
                    'id' => $transicion->id,
                    'anterior' => $transicion->estado_anterior?->etiqueta(),
                    'nuevo' => $transicion->estado_nuevo->etiqueta(),
                    'tono' => $transicion->estado_nuevo->tono(),
                    'icono' => $transicion->estado_nuevo->icono(),
                    'usuario' => $transicion->usuario?->name,
                    // ISO: lo formatea `formatoFechaHora` en el cliente, como
                    // el resto. Cocinarlo aquí ataba el histórico a un formato.
                    'fecha' => $transicion->created_at->toIso8601String(),
                    'nota' => $transicion->nota,
                ])
                ->values()
                ->all(),
            'puedeGestionar' => $this->puede(Permiso::IncidentesGestionar),
            'puedeTratar' => $this->puede(Permiso::NoConformidadesGestionar),
            'puedeMejorar' => $this->puede(Permiso::MejorasGestionar),
        ]);
    }

    public function edit(Incidente $incidente): Response
    {
        $incidente->load('activos');

        return Inertia::render('incidentes/Formulario', [
            'incidente' => $this->serializar($incidente),
            'activosVinculados' => $incidente->activos->pluck('id')->all(),
            'sugerencia' => null,
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarIncidenteRequest $request, Incidente $incidente): RedirectResponse
    {
        $datos = $request->validated();
        $activos = $datos['activos'] ?? null;
        unset($datos['activos']);

        $incidente->update($datos);

        if ($activos !== null) {
            $this->sincronizarActivos($incidente, $activos);
        }

        Inertia::flash('exito', 'Incidente actualizado.');

        return to_route('incidentes.show', $incidente);
    }

    public function destroy(Incidente $incidente): RedirectResponse
    {
        $codigo = $incidente->codigo;
        $incidente->delete();

        Inertia::flash('exito', "Incidente {$codigo} eliminado.");

        return to_route('incidentes.index');
    }

    // --- El ciclo de op.exp.7 ------------------------------------------------

    public function transicion(
        CambiarEstadoIncidenteRequest $request,
        Incidente $incidente,
        CambiarEstadoIncidente $cambiar,
    ): RedirectResponse {
        try {
            $cambiar(
                $incidente,
                EstadoIncidente::from((string) $request->validated('estado')),
                $request->user(),
                $request->string('nota')->value() ?: null,
            );
        } catch (TransicionDeIncidenteNoPermitida $error) {
            return back()->withErrors(['estado' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Incidente actualizado.');

        return back();
    }

    /**
     * La lección aprendida, por su cuenta.
     *
     * **Se escribe mientras se resuelve el incidente**, a trozos y según se va
     * sabiendo, no el día del alta. Obligar a abrir el formulario entero para
     * añadir una línea es cómo se consigue que esa línea no se escriba — y es
     * justo el paso que `op.exp.7` pide y que todo el mundo se salta.
     */
    public function guardarLeccion(GuardarLeccionRequest $request, Incidente $incidente): RedirectResponse
    {
        $incidente->update(['leccion_aprendida' => $request->validated('leccion_aprendida')]);

        Inertia::flash('exito', 'Lección aprendida guardada.');

        return back();
    }

    // --- Las dos notificaciones ---------------------------------------------

    public function notificar(
        RegistrarNotificacionRequest $request,
        Incidente $incidente,
        RegistrarNotificacion $registrar,
    ): RedirectResponse {
        $cuando = $request->date('notificado_en');
        $nota = $request->string('nota')->value() ?: null;

        if ($request->validated('destinatario') === 'aepd') {
            $registrar->aepd($incidente, $cuando, $request->user(), $nota);
            Inertia::flash('exito', 'Notificación a la AEPD registrada.');
        } else {
            $registrar->ccnCert($incidente, $cuando, $request->user(), $nota);
            Inertia::flash('exito', 'Notificación al CCN-CERT registrada.');
        }

        return back();
    }

    /**
     * Los activos afectados, por el modelo y no por los ids a pelo: así pasan por
     * el scope de organización, que es lo que impide colgar del incidente propio
     * el activo de otro cliente.
     *
     * @param  list<int|string>  $ids
     */
    private function sincronizarActivos(Incidente $incidente, array $ids): void
    {
        $validos = Activo::query()->whereIn('id', $ids)->pluck('id')->all();

        $incidente->activos()->sync(array_fill_keys(
            $validos,
            ['organizacion_id' => $incidente->organizacion_id],
        ));
    }

    /**
     * Si el paso deshace algo que alguien ya había dado por hecho, que es lo que
     * exige motivo escrito. La regla vive en `CambiarEstadoIncidente`; esto es
     * sólo lo que el cliente necesita para abrir el cuadro de la nota antes de
     * enviar, como la columna prohibida del tablero de tareas.
     */
    private function retrocede(EstadoIncidente $actual, EstadoIncidente $destino): bool
    {
        return match ([$actual, $destino]) {
            [EstadoIncidente::Cerrado, EstadoIncidente::Resuelto],
            [EstadoIncidente::Resuelto, EstadoIncidente::EnTratamiento],
            [EstadoIncidente::EnTratamiento, EstadoIncidente::Abierto] => true,
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Incidente $incidente): array
    {
        return [
            'id' => $incidente->id,
            'codigo' => $incidente->codigo,
            'titulo' => $incidente->titulo,
            'descripcion' => $incidente->descripcion,
            'sistema_id' => $incidente->sistema_id,
            'sistema' => $incidente->sistema?->codigo,
            'clasificacion' => $incidente->clasificacion->value,
            'clasificacionEtiqueta' => $incidente->clasificacion->etiqueta(),
            'clasificacionTono' => $incidente->clasificacion->tono(),
            'clasificacionIcono' => $incidente->clasificacion->icono(),
            'peligrosidad' => $incidente->peligrosidad->value,
            'peligrosidadEtiqueta' => $incidente->peligrosidad->etiqueta(),
            'peligrosidadTono' => $incidente->peligrosidad->tono(),
            'peligrosidadIcono' => $incidente->peligrosidad->icono(),
            'estado' => $incidente->estado->value,
            'estadoEtiqueta' => $incidente->estado->etiqueta(),
            'estadoTono' => $incidente->estado->tono(),
            'estadoIcono' => $incidente->estado->icono(),
            'fecha_deteccion' => $incidente->fecha_deteccion->format('Y-m-d\TH:i'),
            'fechaDeteccionEtiqueta' => $incidente->fecha_deteccion->format('d/m/Y H:i'),
            'fecha_inicio' => $incidente->fecha_inicio?->format('Y-m-d\TH:i'),
            'fechaInicioEtiqueta' => $incidente->fecha_inicio?->format('d/m/Y H:i'),
            'fechaCierre' => $incidente->fecha_cierre?->format('d/m/Y H:i'),
            'afecta_confidencialidad' => $incidente->afecta_confidencialidad,
            'afecta_integridad' => $incidente->afecta_integridad,
            'afecta_disponibilidad' => $incidente->afecta_disponibilidad,
            'afecta_autenticidad' => $incidente->afecta_autenticidad,
            'afecta_trazabilidad' => $incidente->afecta_trazabilidad,
            'dimensiones' => $incidente->dimensionesAfectadas(),
            'impacto' => $incidente->impacto,
            'acciones_contencion' => $incidente->acciones_contencion,
            'leccion_aprendida' => $incidente->leccion_aprendida,
            'responsable_id' => $incidente->responsable_id,
            'responsable' => $incidente->responsable?->name,
            'notificable_aepd' => $incidente->notificable_aepd,
            'notificable_ccn_cert' => $incidente->notificable_ccn_cert,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'clasificaciones' => array_map(
                static fn (ClasificacionIncidente $clase): array => [
                    'valor' => $clase->value,
                    'etiqueta' => $clase->etiqueta(),
                    'descripcion' => $clase->descripcion(),
                ],
                ClasificacionIncidente::cases(),
            ),
            'peligrosidades' => array_map(
                static fn (PeligrosidadIncidente $nivel): array => [
                    'valor' => $nivel->value,
                    'etiqueta' => $nivel->etiqueta(),
                ],
                PeligrosidadIncidente::cases(),
            ),
            'sistemas' => Sistema::query()
                ->orderBy('codigo')
                ->get()
                ->map(static fn (Sistema $sistema): array => [
                    'valor' => (string) $sistema->id,
                    'etiqueta' => "{$sistema->codigo} · {$sistema->nombre}",
                ])
                ->all(),
            'activosDisponibles' => Activo::query()
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre'])
                ->map(static fn (Activo $activo): array => [
                    'valor' => (string) $activo->id,
                    'etiqueta' => "{$activo->codigo} · {$activo->nombre}",
                ])
                ->all(),
            // Las cinco del Anexo I, con el nombre de su columna como valor:
            // el `FormRequest` deriva los booleanos de ahí, así que el mapa no
            // se escribe dos veces.
            'dimensionesDisponibles' => array_map(
                static fn (string $columna, string $etiqueta): array => [
                    'valor' => $columna,
                    'etiqueta' => $etiqueta,
                ],
                array_keys(Incidente::DIMENSIONES),
                array_values(Incidente::DIMENSIONES),
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
