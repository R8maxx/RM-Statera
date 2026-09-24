<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Conformidad\Enums\EstadoConformidad;
use App\Domain\Conformidad\Excepciones\ConformidadNoPermitida;
use App\Domain\Conformidad\IniciarDeclaracion;
use App\Domain\Conformidad\Models\Conformidad;
use App\Domain\Conformidad\Models\ConformidadTransicion;
use App\Domain\Conformidad\PrepararDocumentoDeclaracion;
use App\Domain\Conformidad\RegistrarDeclaracion;
use App\Domain\Conformidad\RegistrarPublicacionDistintivo;
use App\Domain\Conformidad\RequisitosDeDeclaracion;
use App\Domain\Conformidad\RetirarConformidad;
use App\Domain\Documento\Enums\EstadoDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Requests\RegistrarDeclaracionRequest;
use App\Http\Requests\RegistrarDistintivoRequest;
use App\Http\Requests\RetirarConformidadRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La conformidad con el ENS de cada sistema: § 4.17.
 *
 * **Sin `Recurso` ni tabla paginada**, a diferencia del resto de registros: lo
 * que se lista son los sistemas bajo el ENS, que en una organización son unos
 * pocos, y la pregunta es «¿cómo está cada uno?», no «búscame la declaración
 * número 40». La ficha cuelga del sistema y enseña los tres pasos con lo que
 * bloquea cada uno, más las declaraciones anteriores.
 *
 * Valida, delega en `App\Domain\Conformidad` y devuelve Inertia. Los errores del
 * dominio vuelven al campo como mensaje legible, nunca como un 500.
 */
class ConformidadController extends Controller
{
    public function index(): Response
    {
        $sistemas = $this->sistemasEns()
            ->with(['marco', 'valoraciones'])
            ->orderBy('codigo')
            ->get();

        $conformidades = Conformidad::query()
            ->vivas()
            ->whereIn('sistema_id', $sistemas->pluck('id'))
            ->get()
            ->groupBy('sistema_id');

        return Inertia::render('conformidad/Index', [
            'sistemas' => $sistemas
                ->map(function (Sistema $sistema) use ($conformidades): array {
                    /** @var Collection<int, Conformidad> $vivas */
                    $vivas = $conformidades->get($sistema->id, collect());

                    $vigente = $vivas->first(static fn (Conformidad $c): bool => $c->estado->estaDeclarada());
                    $enPreparacion = $vivas->first(static fn (Conformidad $c): bool => $c->estado === EstadoConformidad::EnPreparacion);

                    return [
                        'id' => $sistema->id,
                        'codigo' => $sistema->codigo,
                        'nombre' => $sistema->nombre,
                        'categoria' => $sistema->categoria()?->etiqueta(),
                        'categoriaTono' => $sistema->categoria()?->value,
                        'vigente' => $vigente === null ? null : $this->resumen($vigente),
                        'enPreparacion' => $enPreparacion === null ? null : $this->resumen($enPreparacion),
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    public function show(Sistema $sistema, RequisitosDeDeclaracion $requisitos): Response
    {
        $sistema->load(['marco', 'valoraciones']);

        $comprobacion = $requisitos->para($sistema);

        $conformidades = Conformidad::query()
            ->where('sistema_id', $sistema->id)
            ->with(['auditoria', 'version.aprobadaPor', 'evidenciaDistintivo', 'transiciones.usuario'])
            ->orderByDesc('id')
            ->get();

        $enPreparacion = $conformidades->first(static fn (Conformidad $c): bool => $c->estado === EstadoConformidad::EnPreparacion);
        $vigente = $conformidades->first(static fn (Conformidad $c): bool => $c->estado->estaDeclarada());
        $documento = PrepararDocumentoDeclaracion::delSistema($sistema);

        return Inertia::render('conformidad/Ficha', [
            'sistema' => [
                'id' => $sistema->id,
                'codigo' => $sistema->codigo,
                'nombre' => $sistema->nombre,
                'marco' => $sistema->marco->nombre,
                'categoria' => $sistema->categoria()?->etiqueta(),
                'categoriaTono' => $sistema->categoria()?->value,
            ],
            'comprobacion' => [
                'bloqueos' => $comprobacion->bloqueos,
                'sePuedeIniciar' => $comprobacion->sePuedeIniciar(),
                'autoevaluacion' => $comprobacion->autoevaluacion === null ? null : [
                    'id' => $comprobacion->autoevaluacion->id,
                    'codigo' => $comprobacion->autoevaluacion->codigo,
                    'fechaCierre' => $comprobacion->autoevaluacion->fecha_cierre?->format('d/m/Y'),
                ],
            ],
            'enPreparacion' => $enPreparacion === null ? null : $this->serializar($enPreparacion),
            'vigente' => $vigente === null ? null : $this->serializar($vigente),
            'anteriores' => $conformidades
                ->filter(static fn (Conformidad $c): bool => $c->estado === EstadoConformidad::Retirada)
                ->map(fn (Conformidad $c): array => $this->serializar($c))
                ->values()
                ->all(),
            'documento' => $documento === null ? null : [
                'id' => $documento->id,
                'codigo' => $documento->codigo,
                'titulo' => $documento->titulo,
            ],
            'versionesFirmadas' => $enPreparacion === null ? [] : $this->versionesFirmadas($enPreparacion),
            'evidencias' => Evidencia::query()
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'titulo'])
                ->map(static fn (Evidencia $evidencia): array => [
                    'valor' => (string) $evidencia->id,
                    'etiqueta' => $evidencia->titulo,
                ])
                ->all(),
            'puedeGestionar' => $this->puede(Permiso::ConformidadGestionar),
            'puedeGenerar' => $this->puede(Permiso::DocumentosGenerar),
        ]);
    }

    public function iniciar(Request $request, Sistema $sistema, IniciarDeclaracion $iniciar): RedirectResponse
    {
        try {
            $iniciar($sistema, $request->user());
        } catch (ConformidadNoPermitida $error) {
            return back()->withErrors(['conformidad' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Declaración de conformidad iniciada. Genera la Declaración y apruébala para firmarla.');

        return to_route('conformidad.show', $sistema);
    }

    public function prepararDocumento(Request $request, Sistema $sistema, PrepararDocumentoDeclaracion $preparar): RedirectResponse
    {
        $documento = $preparar($sistema, $request->user());

        Inertia::flash('exito', "Documento {$documento->codigo} listo. Genera un borrador para ver cómo queda.");

        return to_route('documentos.show', $documento);
    }

    public function declarar(
        RegistrarDeclaracionRequest $request,
        Conformidad $conformidad,
        RegistrarDeclaracion $registrar,
    ): RedirectResponse {
        // Por el modelo y no por el id a pelo: pasa por el scope de organización,
        // así que la versión de otro cliente no existe.
        $version = DocumentoVersion::query()->findOrFail($request->integer('documento_version_id'));

        try {
            $registrar($conformidad, $version, $request->user());
        } catch (ConformidadNoPermitida $error) {
            return back()->withErrors(['documento_version_id' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Conformidad declarada. Queda registrar dónde se publica el distintivo.');

        return to_route('conformidad.show', $conformidad->sistema_id);
    }

    public function distintivo(
        RegistrarDistintivoRequest $request,
        Conformidad $conformidad,
        RegistrarPublicacionDistintivo $registrar,
    ): RedirectResponse {
        $datos = $request->validated();

        try {
            $registrar(
                $conformidad,
                (string) $datos['distintivo_url'],
                Carbon::parse((string) $datos['distintivo_publicado_en']),
                isset($datos['distintivo_evidencia_id']) ? (int) $datos['distintivo_evidencia_id'] : null,
                $request->user(),
            );
        } catch (ConformidadNoPermitida $error) {
            return back()->withErrors(['distintivo_url' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Distintivo registrado.');

        return to_route('conformidad.show', $conformidad->sistema_id);
    }

    public function retirar(
        RetirarConformidadRequest $request,
        Conformidad $conformidad,
        RetirarConformidad $retirar,
    ): RedirectResponse {
        try {
            $retirar($conformidad, (string) $request->validated('motivo'), $request->user());
        } catch (ConformidadNoPermitida $error) {
            return back()->withErrors(['motivo' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Declaración retirada. El motivo queda en su histórico.');

        return to_route('conformidad.show', $conformidad->sistema_id);
    }

    /** @return Builder<Sistema> */
    private function sistemasEns(): Builder
    {
        return Sistema::query()->whereHas('marco', static function (Builder $marco): void {
            $marco->where('codigo', RequisitosDeDeclaracion::CODIGO_MARCO_ENS);
        });
    }

    /**
     * Las versiones emitidas de la Declaración de este sistema que pueden
     * respaldar la declaración en preparación: firmadas después de iniciarla.
     *
     * Es el mismo filtro que `RegistrarDeclaracion` vuelve a comprobar; aquí sólo
     * sirve para no ofrecer en el desplegable lo que el dominio va a rechazar.
     *
     * @return list<array{valor: string, etiqueta: string}>
     */
    private function versionesFirmadas(Conformidad $conformidad): array
    {
        return DocumentoVersion::query()
            ->whereHas('documento', static function (Builder $documento) use ($conformidad): void {
                $documento->where('sistema_id', $conformidad->sistema_id)
                    ->where('tipo', TipoDocumento::DeclaracionConformidadEns->value);
            })
            ->whereNotNull('numero')
            ->where('estado', EstadoDocumental::Aprobado->value)
            ->where('emitida_en', '>=', $conformidad->created_at)
            ->with('aprobadaPor')
            ->orderByDesc('numero')
            ->get()
            ->map(static fn (DocumentoVersion $version): array => [
                'valor' => (string) $version->id,
                'etiqueta' => "v{$version->numero} · firmada el {$version->aprobada_en?->format('d/m/Y')}"
                    .($version->aprobadaPor === null ? '' : " por {$version->aprobadaPor->name}"),
            ])
            ->values()
            ->all();
    }

    /**
     * Lo mínimo para el listado.
     *
     * @return array<string, mixed>
     */
    private function resumen(Conformidad $conformidad): array
    {
        return [
            'id' => $conformidad->id,
            'estado' => $conformidad->estado->value,
            'estadoEtiqueta' => $conformidad->estadoEtiqueta(),
            'tono' => $conformidad->tono(),
            'icono' => $conformidad->haCaducado() ? 'TriangleAlert' : $conformidad->estado->icono(),
            'categoria' => $conformidad->categoria->etiqueta(),
            'vigenteHasta' => $conformidad->vigente_hasta?->format('d/m/Y'),
            'caducada' => $conformidad->haCaducado(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Conformidad $conformidad): array
    {
        $version = $conformidad->version;

        return [
            ...$this->resumen($conformidad),
            'via' => $conformidad->via->etiqueta(),
            'auditoria' => [
                'id' => $conformidad->auditoria->id,
                'codigo' => $conformidad->auditoria->codigo,
                'fechaCierre' => $conformidad->auditoria->fecha_cierre?->format('d/m/Y'),
            ],
            'version' => $version === null ? null : [
                'id' => $version->id,
                'numero' => $version->numero,
                'documentoId' => $version->documento_id,
                'firmadaPor' => $version->aprobadaPor?->name,
                'firmadaEn' => $version->aprobada_en?->format('d/m/Y'),
                'huella' => $version->hash_sha256,
            ],
            'fechaDeclaracion' => $conformidad->fecha_declaracion?->format('d/m/Y'),
            'fechaDeclaracionIso' => $conformidad->fecha_declaracion?->toDateString(),
            'distintivo' => $conformidad->distintivo_url === null ? null : [
                'url' => $conformidad->distintivo_url,
                'publicadoEn' => $conformidad->distintivo_publicado_en?->format('d/m/Y'),
                'evidencia' => $conformidad->evidenciaDistintivo === null ? null : [
                    'id' => $conformidad->evidenciaDistintivo->id,
                    'titulo' => $conformidad->evidenciaDistintivo->titulo,
                ],
            ],
            'iniciadaEn' => $conformidad->created_at->format('d/m/Y'),
            'puedeRetirarse' => $conformidad->estado->permite(EstadoConformidad::Retirada),
            'historial' => $conformidad->transiciones
                ->map(static fn (ConformidadTransicion $transicion): array => [
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
        ];
    }

    private function puede(Permiso $permiso): bool
    {
        return request()->user()?->can($permiso->value) ?? false;
    }
}
