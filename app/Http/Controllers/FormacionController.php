<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Adjunto\BorrarAdjunto;
use App\Domain\Adjunto\Models\Adjunto;
use App\Domain\Adjunto\SubirAdjunto;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Persona\CodigoAccionFormativa;
use App\Domain\Persona\ConvocatoriaDeSesion;
use App\Domain\Persona\DiplomasDeFormacion;
use App\Domain\Persona\Enums\ImparticionFormacion;
use App\Domain\Persona\Enums\JustificacionAusencia;
use App\Domain\Persona\Enums\ModalidadFormacion;
use App\Domain\Persona\Enums\TipoAccionFormativa;
use App\Domain\Persona\Models\AccionFormativa;
use App\Domain\Persona\Models\Persona;
use App\Domain\Persona\RegistrarAsistencia;
use App\Domain\Persona\RegistroFormacion;
use App\Domain\Proveedor\Models\Proveedor;
use App\Http\Controllers\Concerns\GestionaAdjuntos;
use App\Http\Requests\GuardarAccionFormativaRequest;
use App\Http\Requests\RegistrarAsistenciaRequest;
use App\Http\Requests\SubirAdjuntoRequest;
use App\Http\Requests\SubirDiplomaRequest;
use App\Http\Resources\AccionFormativaRecurso;
use App\Http\Resources\Concerns\RespondeConRecurso;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La formación y la concienciación: `mp.per.3` y `mp.per.4`.
 *
 * **Pantalla propia y no un bloque de la ficha de una persona**, por el mismo
 * motivo que la checklist de una auditoría: lo que se registra es una **sesión**
 * con veinte convocados, y marcar veinte asistencias exige marcado en bloque. Al
 * revés —apuntar sesión por sesión desde cada ficha— son veinte peticiones y
 * veinte oportunidades de dejarlo a medias.
 *
 * `/formacion` es la lista de sesiones y `/formacion/{accion}` es la convocatoria
 * de una, con su registro de asistencia.
 */
class FormacionController extends Controller
{
    use GestionaAdjuntos;
    use RespondeConRecurso;

    public function index(Request $request, AccionFormativaRecurso $recurso, RegistroFormacion $registro): Response
    {
        return Inertia::render('formacion/Index', [
            ...$this->tabla($recurso, $request),
            // Las cifras no se cuentan aquí: viven en el dominio, como las de
            // personas e incidentes, y así la clave del indicador es la del
            // filtro por construcción.
            // Vacía siempre, y no por olvido: formación no gasta rojo. Lo que
            // va mal de verdad es una **persona** sin formar, y esa cifra vive
            // en `/personas` con su filtro; repetirla aquí sobre otro
            // denominador daría dos números que parecen el mismo.
            'alertas' => [],
            'pendientes' => $registro->pendientes(),
            'total' => $registro->total(),
        ]);
    }

    /**
     * Con `?desde=` se programa la siguiente de una sesión: mismo título, tipo,
     * duración y contenido, y la fecha al cumplirse la vigencia. Es la
     * concienciación anual, que se repite casi tal cual; lo que no se copia es la
     * convocatoria ni la prueba, que son de la sesión que se imparta.
     *
     * Se busca por el modelo, así que pasa por el scope de organización: un id de
     * otro cliente no encuentra nada y el formulario sale vacío.
     */
    public function create(Request $request, CodigoAccionFormativa $codigos): Response
    {
        $origen = $request->integer('desde') > 0
            ? AccionFormativa::query()->find($request->integer('desde'))
            : null;

        return Inertia::render('formacion/Formulario', [
            'accion' => null,
            'sugerencia' => [
                'codigo' => $codigos->siguiente(),
                'fecha' => $origen?->vigenteHasta()->toDateString() ?? now()->toDateString(),
                'titulo' => $origen?->titulo,
                'tipo' => $origen?->tipo->value,
                'duracion_horas' => $origen?->duracion_horas,
                'contenido' => $origen?->contenido,
                'modalidad' => $origen?->modalidad?->value,
                'imparte' => $origen?->imparte?->value,
                'ponente_persona_id' => $origen?->ponente_persona_id,
                'proveedor_id' => $origen?->proveedor_id,
                'ponente_nombre' => $origen?->ponente_nombre,
            ],
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarAccionFormativaRequest $request): RedirectResponse
    {
        $accion = AccionFormativa::query()->create($request->datos());

        Inertia::flash('exito', "Sesión {$accion->codigo} registrada. Ahora se convoca.");

        return to_route('formacion.show', $accion);
    }

    /**
     * La convocatoria de una sesión.
     *
     * Llegan **todas** las personas activas, no sólo las convocadas: convocar es
     * justamente elegir de esa lista, y con una lista corta hay que ir a otra
     * pantalla a buscar a quien falta. Las dadas de baja que ya estaban
     * convocadas siguen apareciendo —asistieron de verdad y borrarlas reescribiría
     * el registro—, pero no se ofrecen para convocar.
     */
    public function show(AccionFormativa $accion, ConvocatoriaDeSesion $convocatoria, DiplomasDeFormacion $diplomas): Response
    {
        $accion->load(['evidencia', 'adjuntos.subidoPor', 'ponente', 'proveedor']);

        /*
         * Los diplomas salen de las personas y no del material: un adjunto que
         * cuelga también de una persona es su diploma, y en «Material» se
         * mezclaría con el temario.
         */
        $porPersona = $diplomas->deSesion($accion);
        $idsDiplomas = collect($porPersona)->flatten()->map(static fn (Adjunto $adjunto): int => $adjunto->id)->all();
        $accion->setRelation('adjuntos', $accion->adjuntos->reject(static fn (Adjunto $adjunto): bool => in_array($adjunto->id, $idsDiplomas, true))->values());

        $registrada = $accion->asistencias()->max('registrada_en');
        $vigenteHasta = $accion->vigenteHasta();

        return Inertia::render('formacion/Ficha', [
            'accion' => [
                ...$this->serializar($accion),
                'fechaLarga' => $this->fechaLarga($accion->fecha),
                'fechaRelativa' => $accion->fecha->locale('es')->diffForHumans(['parts' => 1]),
                'vigenteHasta' => $vigenteHasta->toDateString(),
                'vigenteHastaLarga' => $this->fechaLarga($vigenteHasta),
                'vigenteHastaRelativa' => $vigenteHasta->locale('es')->diffForHumans(['parts' => 1]),
                'evidenciaFecha' => $accion->evidencia?->fecha_obtencion->format('d/m/Y'),
                'hoy' => Carbon::today()->toDateString(),
                'modalidadEtiqueta' => $accion->modalidad?->etiqueta(),
                'imparteEtiqueta' => $accion->imparte?->etiqueta(),
                'ponente' => $accion->ponente === null ? null : ['id' => $accion->ponente->id, 'nombre' => $accion->ponente->nombre],
                'proveedor' => $accion->proveedor === null ? null : ['id' => $accion->proveedor->id, 'nombre' => $accion->proveedor->nombre],
                'asistenciaRegistrada' => is_string($registrada) ? Carbon::parse($registrada)->format('d/m/Y H:i') : null,
            ],
            'cubre' => $convocatoria->cubre($accion),
            'adjuntos' => $this->serializarAdjuntos($accion, request(), "/formacion/{$accion->id}/adjuntos"),
            'personas' => array_map(
                static fn (array $persona): array => [
                    ...$persona,
                    'diplomas' => array_map(
                        static fn (Adjunto $adjunto): array => [
                            'id' => $adjunto->id,
                            'nombre_fichero' => $adjunto->nombre_fichero,
                            'fecha' => $adjunto->created_at?->format('d/m/Y'),
                        ],
                        $porPersona[$persona['id']] ?? [],
                    ),
                ],
                $convocatoria->personas($accion),
            ),
            'justificaciones' => array_map(
                static fn (JustificacionAusencia $caso): array => [
                    'valor' => $caso->value,
                    'etiqueta' => $caso->etiqueta(),
                    'tono' => $caso->tono(),
                    'icono' => $caso->icono(),
                ],
                JustificacionAusencia::cases(),
            ),
            'puedeGestionar' => request()->user()?->can(Permiso::PersonasGestionar->value) ?? false,
        ]);
    }

    /**
     * El diploma de una persona en esta sesión: un adjunto colgado de las dos.
     * Por qué adjunto y no evidencia está en `DiplomasDeFormacion`.
     */
    public function subirDiploma(
        SubirDiplomaRequest $request,
        AccionFormativa $accion,
        Persona $persona,
        DiplomasDeFormacion $diplomas,
    ): RedirectResponse {
        $diplomas->subir($accion, $persona, $request->file('fichero'), $request->user());

        Inertia::flash('exito', "Diploma de {$persona->nombre} guardado.");

        return back();
    }

    public function edit(AccionFormativa $accion): Response
    {
        return Inertia::render('formacion/Formulario', [
            'accion' => $this->serializar($accion),
            'adjuntos' => $this->serializarAdjuntos($accion, request(), "/formacion/{$accion->id}/adjuntos"),
            'sugerencia' => null,
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarAccionFormativaRequest $request, AccionFormativa $accion): RedirectResponse
    {
        $accion->update($request->datos());

        Inertia::flash('exito', 'Sesión actualizada.');

        return to_route('formacion.show', $accion);
    }

    public function destroy(AccionFormativa $accion): RedirectResponse
    {
        $codigo = $accion->codigo;
        $accion->delete();

        Inertia::flash('exito', "Sesión {$codigo} eliminada.");

        return to_route('formacion.index');
    }

    /**
     * La convocatoria entera, en una sola escritura.
     *
     * Quien sale de la lista deja de estar convocado, que no es lo mismo que haber
     * faltado. Ver `RegistrarAsistencia`.
     */
    public function registrarAsistencia(
        RegistrarAsistenciaRequest $request,
        AccionFormativa $accion,
        RegistrarAsistencia $registrar,
    ): RedirectResponse {
        $registrar($accion, $request->convocadas(), $request->ausencias());

        Inertia::flash('exito', 'Asistencia registrada.');

        return back();
    }

    public function subirAdjunto(SubirAdjuntoRequest $request, AccionFormativa $accion, SubirAdjunto $subir): RedirectResponse
    {
        return $this->subirAdjuntoDe($request, $accion, $subir, 'formacion.show');
    }

    public function descargarAdjunto(AccionFormativa $accion, Adjunto $adjunto): RedirectResponse
    {
        return $this->descargarAdjuntoDe($adjunto);
    }

    public function borrarAdjunto(AccionFormativa $accion, Adjunto $adjunto, BorrarAdjunto $borrar): RedirectResponse
    {
        return $this->borrarAdjuntoDe($adjunto, $borrar, 'formacion.show', $accion);
    }

    /** «12 de marzo de 2026», que es como `DESIGN.md` § 13 pide escribir una fecha. */
    private function fechaLarga(CarbonInterface $fecha): string
    {
        return $fecha->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(AccionFormativa $accion): array
    {
        return [
            'id' => $accion->id,
            'codigo' => $accion->codigo,
            'titulo' => $accion->titulo,
            'tipo' => $accion->tipo->value,
            'tipoEtiqueta' => $accion->tipo->etiqueta(),
            'tipoTono' => $accion->tipo->tono(),
            'tipoIcono' => $accion->tipo->icono(),
            'medida' => $accion->tipo->medida(),
            'fecha' => $accion->fecha->toDateString(),
            'fechaEtiqueta' => $accion->fecha->format('d/m/Y'),
            'duracion_horas' => $accion->duracion_horas,
            'contenido' => $accion->contenido,
            'evidencia_id' => $accion->evidencia_id,
            'evidencia' => $accion->relationLoaded('evidencia') ? $accion->evidencia?->titulo : null,
            'modalidad' => $accion->modalidad?->value,
            'imparte' => $accion->imparte?->value,
            'ponente_persona_id' => $accion->ponente_persona_id,
            'proveedor_id' => $accion->proveedor_id,
            'ponente_nombre' => $accion->ponente_nombre,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'modalidades' => array_map(
                static fn (ModalidadFormacion $caso): array => ['valor' => $caso->value, 'etiqueta' => $caso->etiqueta()],
                ModalidadFormacion::cases(),
            ),
            'imparticiones' => array_map(
                static fn (ImparticionFormacion $caso): array => ['valor' => $caso->value, 'etiqueta' => $caso->etiqueta()],
                ImparticionFormacion::cases(),
            ),
            // Por el modelo, así que pasa por el scope de organización.
            'personas' => Persona::query()
                ->activas()
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->map(static fn (Persona $persona): array => ['valor' => (string) $persona->id, 'etiqueta' => $persona->nombre])
                ->all(),
            'proveedores' => Proveedor::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->map(static fn (Proveedor $proveedor): array => ['valor' => (string) $proveedor->id, 'etiqueta' => $proveedor->nombre])
                ->all(),
            'tipos' => array_map(
                static fn (TipoAccionFormativa $tipo): array => [
                    'valor' => $tipo->value,
                    'etiqueta' => $tipo->etiqueta(),
                    'medida' => $tipo->medida(),
                ],
                TipoAccionFormativa::cases(),
            ),
            /*
             * La hoja de firmas: `mp.per.3` y `mp.per.4` no piden que se
             * imparta la sesión, piden poder demostrarlo.
             *
             * Se adjunta una evidencia que ya está en el repositorio y no se
             * sube una por sesión: el invariante 6 dice que la misma prueba
             * cuenta para todos los marcos donde aplique. Mismo patrón que el
             * bloque de evidencias de una implantación.
             */
            'evidencias' => Evidencia::query()
                ->orderByDesc('fecha_obtencion')
                ->limit(100)
                ->get()
                ->map(static fn (Evidencia $evidencia): array => [
                    'valor' => (string) $evidencia->id,
                    'etiqueta' => $evidencia->titulo,
                ])
                ->all(),
        ];
    }
}
