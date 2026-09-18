<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Contexto\AbrirTareaDeCuestion;
use App\Domain\Contexto\CodigoContexto;
use App\Domain\Contexto\Enums\MateriaCuestion;
use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Contexto\RegistrarCuestion;
use App\Domain\Contexto\RegistroContexto;
use App\Domain\Contexto\RetirarDelAnalisis;
use App\Domain\Contexto\VincularRiesgoACuestion;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Riesgo\Models\Riesgo;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\Plazo;
use App\Http\Requests\AbrirTareaDeCuestionRequest;
use App\Http\Requests\GuardarCuestionRequest;
use App\Http\Requests\RetirarDelContextoRequest;
use App\Http\Requests\VincularEnContextoRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\CuestionRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las cuestiones internas y externas: la trastienda de `/contexto`.
 *
 * Aquí se escribe el DAFO; en `/contexto` se lee. Y desde la ficha salen los dos
 * vínculos que hacen que no sea un papel suelto: el riesgo que la cuestión abrió y
 * el trabajo que genera.
 *
 * **Retirar no es borrar**, y por eso son dos rutas distintas con dos textos
 * distintos. Borrar se lleva por delante los riesgos y las tareas que colgaban de
 * la cuestión y deja sin explicar por qué el análisis de este año tiene una
 * cuestión menos que el del anterior; retirar lo explica, que es lo que la cláusula
 * 9.3 va a preguntar.
 */
class CuestionContextoController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, CuestionRecurso $recurso, RegistroContexto $registro): Response
    {
        return Inertia::render('contexto/Cuestiones', [
            ...$this->tabla($recurso, $request),
            'alertas' => $registro->alertasCuestiones(),
            'pendientes' => $registro->pendientesCuestiones(),
            'total' => $registro->totalCuestiones(),
        ]);
    }

    public function create(Request $request, CodigoContexto $codigos): Response
    {
        $tipo = TipoCuestion::tryFrom((string) $request->query('tipo'));

        return Inertia::render('contexto/CuestionFormulario', [
            'cuestion' => null,
            'sugerencia' => [
                'codigo' => $codigos->siguienteCuestion(),
                'tipo' => ($tipo ?? TipoCuestion::Debilidad)->value,
            ],
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarCuestionRequest $request, RegistrarCuestion $registrar): RedirectResponse
    {
        $cuestion = $registrar($request->validated(), $request->user());

        Inertia::flash('exito', "Cuestión {$cuestion->codigo} registrada.");

        return to_route('contexto.cuestiones.show', $cuestion);
    }

    public function show(CuestionContexto $cuestion): Response
    {
        $cuestion->load([
            'responsable',
            'analisisAlta',
            'analisisBaja',
            'riesgos.valoracionVigente',
            'tareas.responsable',
        ]);

        return Inertia::render('contexto/CuestionFicha', [
            'cuestion' => [
                'id' => $cuestion->id,
                'codigo' => $cuestion->codigo,
                'titulo' => $cuestion->titulo,
                'descripcion' => $cuestion->descripcion,
                'tipo' => [
                    'valor' => $cuestion->tipo->value,
                    'etiqueta' => $cuestion->tipo->etiqueta(),
                    'tono' => $cuestion->tipo->tono(),
                    'icono' => $cuestion->tipo->icono(),
                ],
                'ambito' => $cuestion->ambito()->etiqueta(),
                'ambitoAyuda' => $cuestion->ambito()->ayuda(),
                'signo' => $cuestion->signo()->etiqueta(),
                'materia' => $cuestion->materia->etiqueta(),
                'esClimatica' => $cuestion->es_climatica,
                'responsable' => $cuestion->responsable?->name,
                'vigente' => $cuestion->estaVigente(),
                'motivoBaja' => $cuestion->motivo_baja,
                'altaEn' => $cuestion->analisisAlta?->etiqueta(),
                'bajaEn' => $cuestion->analisisBaja?->etiqueta(),
            ],
            'riesgos' => $cuestion->riesgos->map(fn (Riesgo $riesgo): array => [
                'id' => $riesgo->id,
                'codigo' => $riesgo->codigo,
                'titulo' => $riesgo->titulo,
            ])->all(),
            'tareas' => $cuestion->tareas->map(function (Tarea $tarea): array {
                $plazo = Plazo::para(
                    $tarea->fecha_limite,
                    $tarea->estado->esCerrada(),
                    $tarea->fecha_cierre,
                    $tarea->haVencido(),
                );

                return [
                    'id' => $tarea->id,
                    'titulo' => $tarea->titulo,
                    'estado' => $tarea->estado->etiqueta(),
                    'estadoTono' => $tarea->estado->tono(),
                    'estadoIcono' => $tarea->estado->icono(),
                    'responsable' => $tarea->responsable?->name,
                    'plazoEtiqueta' => $plazo->etiqueta,
                    'plazoTono' => $plazo->tono,
                ];
            })->all(),
            'puedeGestionar' => $this->puede(Permiso::ContextoGestionar),
            'prioridades' => array_map(static fn (PrioridadTarea $prioridad): array => [
                'valor' => $prioridad->value,
                'etiqueta' => $prioridad->etiqueta(),
            ], PrioridadTarea::cases()),
            /*
             * Lo que se puede vincular, ya sin lo que está vinculado: ofrecer algo
             * que va a rebotar contra el índice único de la pivote es enseñar una
             * opción que no hace nada.
             */
            'riesgosDisponibles' => Riesgo::query()
                ->whereNotIn('id', $cuestion->riesgos->pluck('id'))
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'titulo'])
                ->map(static fn (Riesgo $riesgo): array => [
                    'valor' => (string) $riesgo->id,
                    'etiqueta' => "{$riesgo->codigo} · {$riesgo->titulo}",
                ])
                ->all(),
            'tareasDisponibles' => Tarea::query()
                ->abiertas()
                ->whereNotIn('id', $cuestion->tareas->pluck('id'))
                ->orderBy('titulo')
                ->get(['id', 'titulo'])
                ->map(static fn (Tarea $tarea): array => [
                    'valor' => (string) $tarea->id,
                    'etiqueta' => $tarea->titulo,
                ])
                ->all(),
            ...$this->opciones(),
        ]);
    }

    public function edit(CuestionContexto $cuestion): Response
    {
        return Inertia::render('contexto/CuestionFormulario', [
            'cuestion' => [
                'id' => $cuestion->id,
                'codigo' => $cuestion->codigo,
                'tipo' => $cuestion->tipo->value,
                'titulo' => $cuestion->titulo,
                'descripcion' => $cuestion->descripcion,
                'materia' => $cuestion->materia->value,
                'es_climatica' => $cuestion->es_climatica,
                'responsable_id' => $cuestion->responsable_id,
            ],
            'sugerencia' => null,
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarCuestionRequest $request, CuestionContexto $cuestion): RedirectResponse
    {
        $cuestion->update($request->validated());

        Inertia::flash('exito', 'Cuestión actualizada.');

        return to_route('contexto.cuestiones.show', $cuestion);
    }

    public function destroy(CuestionContexto $cuestion): RedirectResponse
    {
        $cuestion->delete();

        Inertia::flash('exito', 'Cuestión eliminada.');

        return to_route('contexto.cuestiones.index');
    }

    /** Retirar: la baja con su motivo, contra el borrador que haya abierto. */
    public function retirar(
        RetirarDelContextoRequest $request,
        CuestionContexto $cuestion,
        RetirarDelAnalisis $retirar,
    ): RedirectResponse {
        $retirar->cuestion($cuestion, $request->string('motivo')->value(), $request->user());

        Inertia::flash('exito', "Cuestión {$cuestion->codigo} retirada. Queda en el registro con su motivo.");

        return back();
    }

    public function vincularRiesgo(
        VincularEnContextoRequest $request,
        CuestionContexto $cuestion,
        VincularRiesgoACuestion $vinculos,
    ): RedirectResponse {
        /*
         * Por el modelo y no copiando el id: así pasa por el scope de organización
         * y por RLS, que es lo que impide colgar el riesgo de otro cliente pasando
         * su identificador a mano. Lo ajeno da 404, nunca 403.
         */
        $riesgo = Riesgo::query()->findOrFail($request->integer('riesgo_id'));

        $vinculos->vincular($cuestion, $riesgo, $request->user());

        Inertia::flash('exito', "Riesgo {$riesgo->codigo} vinculado a la cuestión.");

        return back();
    }

    public function desvincularRiesgo(
        CuestionContexto $cuestion,
        Riesgo $riesgo,
        VincularRiesgoACuestion $vinculos,
    ): RedirectResponse {
        $vinculos->desvincular($cuestion, $riesgo);

        Inertia::flash('exito', 'Riesgo desvinculado.');

        return back();
    }

    public function abrirTarea(
        AbrirTareaDeCuestionRequest $request,
        CuestionContexto $cuestion,
        AbrirTareaDeCuestion $abrir,
    ): RedirectResponse {
        $tarea = $abrir($cuestion, $request->validated(), $request->user());

        Inertia::flash('exito', "Tarea «{$tarea->titulo}» abierta y vinculada a la cuestión.");

        return back();
    }

    public function vincularTarea(
        VincularEnContextoRequest $request,
        CuestionContexto $cuestion,
        AbrirTareaDeCuestion $vinculos,
    ): RedirectResponse {
        $tarea = Tarea::query()->findOrFail($request->integer('tarea_id'));

        $vinculos->vincular($cuestion, $tarea, $request->user());

        Inertia::flash('exito', 'Tarea vinculada a la cuestión.');

        return back();
    }

    public function desvincularTarea(
        CuestionContexto $cuestion,
        Tarea $tarea,
        AbrirTareaDeCuestion $vinculos,
    ): RedirectResponse {
        $vinculos->desvincular($cuestion, $tarea);

        Inertia::flash('exito', 'Tarea desvinculada. La tarea sigue en el plan de acción.');

        return back();
    }

    /**
     * Los desplegables que comparten el alta, la edición y la ficha.
     *
     * **Los usuarios se acotan a mano**: `User` no lleva `PerteneceAOrganizacion`
     * —la autenticación tiene que poder encontrar a alguien antes de saber de qué
     * organización es—, así que aquí no hay scope global ni RLS que tapen el cruce
     * y sin este `where` el desplegable lista a los usuarios de todos los clientes.
     *
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'tipos' => array_map(static fn (TipoCuestion $tipo): array => [
                'valor' => $tipo->value,
                'etiqueta' => $tipo->etiqueta(),
                'tono' => $tipo->tono(),
                'icono' => $tipo->icono(),
                'ambito' => $tipo->ambito()->etiqueta(),
            ], TipoCuestion::enOrdenDeMatriz()),

            'materias' => array_map(static fn (MateriaCuestion $materia): array => [
                'valor' => $materia->value,
                'etiqueta' => $materia->etiqueta(),
            ], MateriaCuestion::cases()),

            'usuarios' => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get(['id', 'name'])
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
