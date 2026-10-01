<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Comunicacion\CodigoComunicacionPrevista;
use App\Domain\Comunicacion\Enums\CanalComunicacion;
use App\Domain\Comunicacion\Enums\SentidoComunicacion;
use App\Domain\Comunicacion\Excepciones\ComunicacionInvalida;
use App\Domain\Comunicacion\Models\Comunicacion;
use App\Domain\Comunicacion\Models\ComunicacionPrevista;
use App\Domain\Comunicacion\RegistrarComunicacion;
use App\Domain\Comunicacion\RegistroComunicacion;
use App\Domain\Comunicacion\SincronizarDestinatarios;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Requests\GuardarComunicacionPrevistaRequest;
use App\Http\Requests\GuardarComunicacionRequest;
use App\Http\Requests\RetirarComunicacionPrevistaRequest;
use App\Http\Resources\ComunicacionPrevistaRecurso;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El plan de comunicación: la cláusula 7.4.
 *
 * **Dos permisos y no tres**: un plan de comunicación no se firma, y lo que se
 * registra son hechos. Quien lleva el sistema lo lleva entero.
 *
 * Desde la ficha de una línea se registra lo que se comunicó para cumplirla, que
 * es lo que mueve su próxima fecha. Lo recibido no cuelga de ninguna línea y se
 * registra en `/comunicaciones`.
 */
class ComunicacionPrevistaController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, ComunicacionPrevistaRecurso $recurso, RegistroComunicacion $registro): Response
    {
        return Inertia::render('plan-comunicacion/Index', [
            ...$this->tabla($recurso, $request),
            'alertas' => $registro->alertas(),
            'pendientes' => $registro->pendientes(),
            'total' => $registro->total(),
        ]);
    }

    public function create(CodigoComunicacionPrevista $codigos): Response
    {
        return Inertia::render('plan-comunicacion/Formulario', [
            'prevista' => null,
            'sugerencia' => [
                'codigo' => $codigos->siguiente(),
                'computa_desde' => now()->toDateString(),
            ],
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarComunicacionPrevistaRequest $request, SincronizarDestinatarios $destinatarios): RedirectResponse
    {
        $prevista = DB::transaction(function () use ($request, $destinatarios): ComunicacionPrevista {
            $prevista = ComunicacionPrevista::query()->create($request->datos());
            $destinatarios($prevista, $request->partesInteresadas());

            return $prevista;
        });

        Inertia::flash('exito', "Comunicación {$prevista->codigo} añadida al plan.");

        return to_route('plan-comunicacion.show', $prevista);
    }

    public function show(ComunicacionPrevista $prevista): Response
    {
        $prevista->load(['responsable', 'partesInteresadas', 'comunicaciones.registradaPor', 'comunicaciones.evidencia']);

        return Inertia::render('plan-comunicacion/Ficha', [
            'prevista' => $this->serializar($prevista),
            'comunicaciones' => $prevista->comunicaciones
                ->map(fn (Comunicacion $comunicacion): array => [
                    'id' => $comunicacion->id,
                    'fecha' => $comunicacion->fecha->toDateString(),
                    'cubreHasta' => $comunicacion->cubre_hasta?->toDateString(),
                    'asunto' => $comunicacion->asunto,
                    'resumen' => $comunicacion->resumen,
                    'canal' => $comunicacion->canal->etiqueta(),
                    'evidencia' => $comunicacion->evidencia?->titulo,
                    'evidenciaId' => $comunicacion->evidencia_id,
                    'registradaPor' => $comunicacion->registradaPor?->name,
                    'registradaEn' => $comunicacion->created_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'evidencias' => $this->evidencias(),
            'puedeGestionar' => $this->puede(Permiso::ComunicacionGestionar),
            ...$this->opciones(),
        ]);
    }

    public function edit(ComunicacionPrevista $prevista): Response
    {
        $prevista->load('partesInteresadas');

        return Inertia::render('plan-comunicacion/Formulario', [
            'prevista' => $this->serializar($prevista),
            'sugerencia' => null,
            ...$this->opciones(),
        ]);
    }

    public function update(
        GuardarComunicacionPrevistaRequest $request,
        ComunicacionPrevista $prevista,
        SincronizarDestinatarios $destinatarios,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $prevista, $destinatarios): void {
            $prevista->update($request->datos());
            $destinatarios($prevista, $request->partesInteresadas());
        });

        Inertia::flash('exito', 'Comunicación prevista actualizada.');

        return to_route('plan-comunicacion.show', $prevista);
    }

    public function destroy(ComunicacionPrevista $prevista): RedirectResponse
    {
        // Lo que se comunicó se queda: la clave foránea es `nullOnDelete`.
        $codigo = $prevista->codigo;
        $prevista->delete();

        Inertia::flash('exito', "Comunicación {$codigo} eliminada del plan. Lo que ya se comunicó sigue registrado.");

        return to_route('plan-comunicacion.index');
    }

    public function retirar(RetirarComunicacionPrevistaRequest $request, ComunicacionPrevista $prevista): RedirectResponse
    {
        $prevista->update([
            'retirada_en' => Carbon::today(),
            'motivo_retirada' => $request->validated('motivo_retirada'),
        ]);

        Inertia::flash('exito', "Comunicación {$prevista->codigo} retirada del plan.");

        return back();
    }

    public function reactivar(ComunicacionPrevista $prevista): RedirectResponse
    {
        $prevista->update(['retirada_en' => null, 'motivo_retirada' => null]);

        Inertia::flash('exito', "Comunicación {$prevista->codigo} de vuelta en el plan.");

        return back();
    }

    /**
     * Lo que se comunicó para cumplir esta línea del plan.
     */
    public function comunicar(
        GuardarComunicacionRequest $request,
        ComunicacionPrevista $prevista,
        RegistrarComunicacion $registrar,
    ): RedirectResponse {
        $datos = $request->validated();

        try {
            $registrar(
                SentidoComunicacion::Emitida,
                Carbon::parse((string) $datos['fecha']),
                array_intersect_key($datos, array_flip(['asunto', 'resumen', 'canal', 'evidencia_id'])),
                $request->user(),
                $prevista,
            );
        } catch (ComunicacionInvalida $error) {
            return back()->withErrors(['fecha' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Comunicación registrada.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(ComunicacionPrevista $prevista): array
    {
        $proxima = ComunicacionPrevistaRecurso::proxima($prevista);

        return [
            'id' => $prevista->id,
            'codigo' => $prevista->codigo,
            'titulo' => $prevista->titulo,
            'descripcion' => $prevista->descripcion,
            'canal' => $prevista->canal->value,
            'canalEtiqueta' => $prevista->canal->etiqueta(),
            'responsable_id' => $prevista->responsable_id,
            'responsable' => $prevista->responsable?->name,
            'periodicidad_meses' => $prevista->periodicidad_meses,
            'cadencia' => $prevista->cadencia()?->etiqueta() ?? 'Cuando proceda',
            'computa_desde' => $prevista->computa_desde?->toDateString(),
            'destinatarios_otros' => $prevista->destinatarios_otros,
            'partes_interesadas' => $prevista->partesInteresadas->pluck('id')->map(fn (int $id): string => (string) $id)->all(),
            'partes' => $prevista->partesInteresadas->pluck('nombre')->all(),
            'retirada' => $prevista->estaRetirada(),
            'retiradaEn' => $prevista->retirada_en?->format('d/m/Y'),
            'motivoRetirada' => $prevista->motivo_retirada,
            'proxima' => [
                'etiqueta' => $proxima->etiqueta,
                'tono' => $proxima->tono,
            ],
        ];
    }

    /**
     * Las opciones de los desplegables.
     *
     * Los responsables van acotados a la organización a mano: `User` no lleva
     * `PerteneceAOrganizacion`. Las partes interesadas, sólo las vigentes.
     *
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'canales' => array_map(
                static fn (CanalComunicacion $canal): array => ['valor' => $canal->value, 'etiqueta' => $canal->etiqueta()],
                CanalComunicacion::cases(),
            ),
            'cadencias' => ComunicacionPrevistaRecurso::cadencias(),
            'partesInteresadas' => ParteInteresada::query()
                ->vigentes()
                ->orderBy('nombre')
                ->get()
                ->map(static fn (ParteInteresada $parte): array => [
                    'valor' => (string) $parte->id,
                    'etiqueta' => $parte->nombre,
                    'descripcion' => $parte->tipo->etiqueta(),
                ])
                ->all(),
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

    /**
     * Las evidencias que pueden probar una comunicación: las cien más recientes,
     * como en el cumplimiento de un compromiso.
     *
     * @return list<array{valor: string, etiqueta: string}>
     */
    private function evidencias(): array
    {
        return Evidencia::query()
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(static fn (Evidencia $evidencia): array => ['valor' => (string) $evidencia->id, 'etiqueta' => $evidencia->titulo])
            ->values()
            ->all();
    }

    private function puede(Permiso $permiso): bool
    {
        return request()->user()?->can($permiso->value) ?? false;
    }
}
