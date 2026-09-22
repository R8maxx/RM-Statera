<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Activo\Models\Activo;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Riesgo\AceptarRiesgo;
use App\Domain\Riesgo\CalculoRiesgo;
use App\Domain\Riesgo\CoberturaSalvaguardas;
use App\Domain\Riesgo\CrearRiesgo;
use App\Domain\Riesgo\Enums\DecisionRiesgo;
use App\Domain\Riesgo\ImpactoIntrinseco;
use App\Domain\Riesgo\MetodologiaVigente;
use App\Domain\Riesgo\Models\Amenaza;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Riesgo\Models\RiesgoValoracion;
use App\Domain\Riesgo\RegistroRiesgos;
use App\Domain\Riesgo\ValorarRiesgo;
use App\Domain\Riesgo\VincularRiesgo;
use App\Http\Requests\GuardarRiesgoRequest;
use App\Http\Requests\ValorarRiesgoRequest;
use App\Http\Requests\VincularSalvaguardaRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\RiesgoRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El registro de riesgos.
 *
 * Delgado como el resto: valida, delega y devuelve Inertia. Todo lo que decide algo
 * —jubilar la valoración anterior, congelar la escala, exigir un activo, negarse a
 * firmar sin residual— vive en `app/Domain/Riesgo/`.
 *
 * La ficha enseña **las dos cifras juntas** y, al lado, lo que la herramienta
 * deduce sin sobrescribir nada: el impacto que sugieren los activos y el respaldo
 * real de las salvaguardas. Es el mismo trato que la ficha de un activo da a la
 * valoración propia y a la efectiva.
 */
class RiesgoController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, RiesgoRecurso $recurso, RegistroRiesgos $registro): Response
    {
        return Inertia::render('riesgos/Index', [
            ...$this->tabla($recurso, $request),

            // Dos listas y no una: incumplimiento real y trabajo sin hacer no se
            // leen igual, y `TiraIndicadores` los pinta con pesos distintos.
            'alertas' => $registro->alertas(),
            'pendientes' => $registro->pendientes(),
            'total' => $registro->total(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('riesgos/Formulario', [
            'riesgo' => null,
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarRiesgoRequest $request, CrearRiesgo $crear): RedirectResponse
    {
        $datos = $request->validated();

        /** @var list<int> $ids */
        $ids = $datos['activos'];
        unset($datos['activos']);

        $activos = Activo::query()->findMany($ids)->all();

        $riesgo = $crear($datos, array_values($activos), $request->user());

        Inertia::flash('exito', "Riesgo {$riesgo->codigo} registrado. Todavía sin valorar.");

        return to_route('riesgos.show', $riesgo);
    }

    public function show(
        Riesgo $riesgo,
        MetodologiaVigente $metodologias,
        ImpactoIntrinseco $impacto,
        CoberturaSalvaguardas $cobertura,
        CalculoRiesgo $calculo,
    ): Response {
        $riesgo->load(['amenaza', 'propietario', 'activos', 'salvaguardas.requisito', 'valoracionVigente']);

        $metodologia = $metodologias->para();

        return Inertia::render('riesgos/Ficha', [
            'riesgo' => $this->serializar($riesgo),
            'valoracion' => $this->serializarValoracion($riesgo->valoracionVigente),
            'historico' => $riesgo->valoraciones()->get()->map(
                fn (RiesgoValoracion $valoracion): array => $this->serializarValoracion($valoracion) ?? [],
            )->all(),

            /*
             * Lo que la herramienta deduce, junto a lo que declaró la persona y sin
             * sustituirlo. `sugerido` es el impacto que sale de la valoración
             * efectiva de los activos; `motivos` dice qué activo pone el techo, sin
             * lo cual la cifra parece un error.
             */
            'sugerencia' => [
                'impacto' => $impacto->sugerido($riesgo, $metodologia),
                'motivos' => array_map(
                    static fn (array $motivo): array => [
                        'activo' => $motivo['activo']->nombre,
                        'dimensiones' => array_map(
                            static fn ($dimension): string => $dimension->nombre(),
                            $motivo['dimensiones'],
                        ),
                    ],
                    $impacto->motivos($riesgo),
                ),
            ],
            'cobertura' => $cobertura->de($riesgo),
            'sinRespaldo' => $cobertura->sinRespaldo($riesgo),

            'metodologia' => [
                'nombre' => $metodologia->nombre,
                'esDeFabrica' => $metodologia->esDeFabrica,
                'estaAprobada' => $metodologia->estaAprobada(),
                'probabilidad' => $metodologia->probabilidad->aArray(),
                'impacto' => $metodologia->impacto->aArray(),
                'umbralAceptacion' => $metodologia->umbralAceptacion,
                'umbralCritico' => $metodologia->umbralCritico,
                'bandas' => $calculo->bandas($metodologia),
            ],

            'decisiones' => array_map(
                static fn (DecisionRiesgo $decision): array => [
                    'valor' => $decision->value,
                    'etiqueta' => $decision->etiqueta(),
                    'descripcion' => $decision->descripcion(),
                    'tono' => $decision->tono(),
                    'icono' => $decision->icono(),
                ],
                DecisionRiesgo::cases(),
            ),

            'candidatos' => $this->candidatosASalvaguarda($riesgo),
        ]);
    }

    public function edit(Riesgo $riesgo): Response
    {
        return Inertia::render('riesgos/Formulario', [
            'riesgo' => $this->serializar($riesgo->load('activos')),
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarRiesgoRequest $request, Riesgo $riesgo, VincularRiesgo $vinculos): RedirectResponse
    {
        $datos = $request->validated();

        /** @var list<int> $ids */
        $ids = $datos['activos'];
        unset($datos['activos']);

        $riesgo->update($datos);

        // `sync` y no el vincular de uno en uno: aquí llega la lista entera, y el
        // dominio ya impide dejarla vacía porque el FormRequest exige `min:1`.
        $riesgo->activos()->sync(array_fill_keys($ids, [
            'organizacion_id' => $riesgo->organizacion_id,
            'vinculado_por_id' => $request->user()?->id,
        ]));

        Inertia::flash('exito', "Riesgo {$riesgo->codigo} actualizado.");

        return to_route('riesgos.show', $riesgo);
    }

    public function destroy(Riesgo $riesgo): RedirectResponse
    {
        $codigo = $riesgo->codigo;
        $riesgo->delete();

        Inertia::flash('exito', "Riesgo {$codigo} eliminado.");

        return to_route('riesgos.index');
    }

    public function valorar(ValorarRiesgoRequest $request, Riesgo $riesgo, ValorarRiesgo $valorar): RedirectResponse
    {
        /** @var array{probabilidad: int, impacto: int} $datos */
        $datos = $request->validated();

        $valorar($riesgo, $datos, $request->user());

        Inertia::flash('exito', 'Riesgo valorado. La valoración anterior queda en el histórico.');

        return to_route('riesgos.show', $riesgo);
    }

    public function aceptar(Request $request, Riesgo $riesgo, AceptarRiesgo $aceptar): RedirectResponse
    {
        $datos = $request->validate(['nota' => ['nullable', 'string', 'max:1000']]);

        /** @var User $usuario */
        $usuario = $request->user();

        $aceptar($riesgo->load('valoracionVigente'), $usuario, $datos['nota'] ?? null);

        Inertia::flash('exito', 'Riesgo aceptado. A partir de ahora la valoración es inmutable.');

        return back();
    }

    public function vincularSalvaguarda(
        VincularSalvaguardaRequest $request,
        Riesgo $riesgo,
        VincularRiesgo $vinculos,
    ): RedirectResponse {
        $implantacion = Implantacion::query()->findOrFail($request->integer('implantacion_id'));

        $vinculos->salvaguarda($riesgo, $implantacion, $request->string('nota')->value() ?: null, $request->user());

        Inertia::flash('exito', 'Salvaguarda vinculada.');

        return back();
    }

    public function desvincularSalvaguarda(
        Riesgo $riesgo,
        Implantacion $implantacion,
        VincularRiesgo $vinculos,
    ): RedirectResponse {
        $vinculos->desvincularSalvaguarda($riesgo, $implantacion);

        Inertia::flash('exito', 'Salvaguarda desvinculada.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Riesgo $riesgo): array
    {
        return [
            'id' => $riesgo->id,
            'codigo' => $riesgo->codigo,
            'titulo' => $riesgo->titulo,
            'amenaza_id' => $riesgo->amenaza_id,
            'amenaza_libre' => $riesgo->amenaza_libre,
            'amenaza' => $riesgo->nombreAmenaza(),
            'vulnerabilidad' => $riesgo->vulnerabilidad,
            'propietario_id' => $riesgo->propietario_id,
            'propietario' => $riesgo->propietario?->name,
            'fecha_revision' => $riesgo->fecha_revision?->toDateString(),
            'revision_vencida' => $riesgo->revisionVencida(),
            'notas' => $riesgo->notas,
            'activos' => $riesgo->activos->map(fn (Activo $activo): array => [
                'id' => $activo->id,
                'codigo' => $activo->codigo,
                'nombre' => $activo->nombre,
                'tipo' => $activo->tipo->value,
            ])->all(),
            'salvaguardas' => $riesgo->salvaguardas->map(fn (Implantacion $implantacion): array => [
                'id' => $implantacion->id,
                'requisito' => $implantacion->requisito?->codigo,
                'titulo' => $implantacion->requisito?->titulo,
                'estado' => $implantacion->estado->value,
                'estado_etiqueta' => $implantacion->estado->etiqueta(),
                'estado_tono' => $implantacion->estado->tono(),
                'estado_icono' => $implantacion->estado->icono(),
                'madurez' => $implantacion->nivel_madurez?->etiqueta(),
                'nota' => $implantacion->getAttribute('pivot')?->nota,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializarValoracion(?RiesgoValoracion $valoracion): ?array
    {
        if ($valoracion === null) {
            return null;
        }

        $intrinseco = $valoracion->nivelIntrinseco();
        $residual = $valoracion->nivelResidual();

        return [
            'id' => $valoracion->id,
            'vigente' => $valoracion->vigente,
            'probabilidad' => $valoracion->probabilidad,
            'impacto' => $valoracion->impacto,
            'impacto_por_dimension' => $valoracion->impacto_por_dimension,
            'intrinseco' => $valoracion->riesgo_intrinseco,
            'intrinseco_nivel' => ['valor' => $intrinseco->value, 'etiqueta' => $intrinseco->etiqueta(), 'tono' => $intrinseco->tono(), 'icono' => $intrinseco->icono()],
            'probabilidad_residual' => $valoracion->probabilidad_residual,
            'impacto_residual' => $valoracion->impacto_residual,
            'residual' => $valoracion->riesgo_residual,
            'residual_nivel' => $residual === null ? null : ['valor' => $residual->value, 'etiqueta' => $residual->etiqueta(), 'tono' => $residual->tono(), 'icono' => $residual->icono()],
            'justificacion_residual' => $valoracion->justificacion_residual,
            'decision' => $valoracion->decision->value,
            'decision_etiqueta' => $valoracion->decision->etiqueta(),
            'decision_tono' => $valoracion->decision->tono(),
            'decision_icono' => $valoracion->decision->icono(),
            'valorada_en' => $valoracion->valorada_en->toDateString(),
            'valorada_por' => $valoracion->valoradaPor?->name,
            'aceptada_en' => $valoracion->aceptada_en?->toDateString(),
            'aceptada_por' => $valoracion->aceptadaPor?->name,
            'nota' => $valoracion->nota,
            'nota_aceptacion' => $valoracion->nota_aceptacion,
            'editable' => $valoracion->esEditable(),

            // La instantánea de las salvaguardas: lo que se enseña al comparar dos
            // valoraciones, y lo que impide que la fila de marzo mienta en octubre.
            'salvaguardas' => $valoracion->salvaguardas,
        ];
    }

    /**
     * Los controles que se pueden apoyar contra este riesgo.
     *
     * Sólo los **aplicables**: un requisito que el motor de categorización ha
     * excluido no protege de nada, y ofrecerlo invitaría a montar un tratamiento
     * sobre algo que a este sistema no se le exige. Y sin los ya vinculados, para
     * que la lista no repita lo que está justo encima.
     *
     * @return list<array<string, mixed>>
     */
    private function candidatosASalvaguarda(Riesgo $riesgo): array
    {
        return Implantacion::query()
            ->where('aplica', true)
            ->whereNotIn('id', $riesgo->salvaguardas->pluck('id'))
            ->with('requisito.marco')
            ->get()
            ->sortBy(fn (Implantacion $implantacion): string => (string) $implantacion->requisito?->codigo)
            ->map(fn (Implantacion $implantacion): array => [
                'valor' => (string) $implantacion->id,
                'etiqueta' => trim(sprintf(
                    '%s — %s',
                    (string) $implantacion->requisito?->codigo,
                    (string) $implantacion->requisito?->titulo,
                )),
                'marco' => $implantacion->requisito?->marco?->codigo,
                'estado' => $implantacion->estado->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'amenazas' => Amenaza::query()
                ->vigentes()
                ->orderBy('grupo')
                ->orderBy('orden')
                ->get()
                ->map(fn (Amenaza $amenaza): array => [
                    'valor' => (string) $amenaza->id,
                    'etiqueta' => $amenaza->etiqueta(),
                    'grupo' => $amenaza->grupo->etiqueta(),
                    'tipos_activo' => $amenaza->tipos_activo,
                ])
                ->all(),
            'activos' => Activo::query()
                ->vigentes()
                ->orderBy('nombre')
                ->get()
                ->map(fn (Activo $activo): array => [
                    'valor' => (string) $activo->id,
                    'etiqueta' => $activo->nombre,
                    'tipo' => $activo->tipo->value,
                ])
                ->all(),
            'personas' => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): array => [
                    'valor' => (string) $usuario->id,
                    'etiqueta' => $usuario->name,
                ])
                ->all(),
        ];
    }
}
