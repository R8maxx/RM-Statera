<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Evidencia\VincularEvidencia;
use App\Domain\Implantacion\CambiarAplicabilidad;
use App\Domain\Implantacion\CambiarEstado;
use App\Domain\Implantacion\CorrespondenciasCruzadas;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Enums\NivelMadurez;
use App\Domain\Implantacion\Excepciones\ExclusionNoPermitida;
use App\Domain\Implantacion\Excepciones\TransicionNoPermitida;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Implantacion\Models\ImplantacionTransicion;
use App\Domain\Tarea\Models\Tarea;
use App\Http\Requests\CambiarEstadoImplantacionesRequest;
use App\Http\Requests\CambiarEstadoImplantacionRequest;
use App\Http\Requests\GuardarImplantacionRequest;
use App\Http\Requests\VincularEvidenciaRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\ImplantacionRecurso;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImplantacionController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request): Response
    {
        return Inertia::render('implantaciones/Index', $this->tabla(new ImplantacionRecurso, $request));
    }

    /**
     * La ficha: qué aplica, cómo se cumple y desde cuándo.
     *
     * Las tres preguntas de una auditoría en una pantalla. La tercera —dónde
     * está la prueba— entra con el módulo de evidencias.
     */
    public function show(
        Implantacion $implantacion,
        CambiarAplicabilidad $aplicabilidad,
        CorrespondenciasCruzadas $correspondencias,
    ): Response {
        $implantacion->load([
            'requisito.marco',
            'sistema',
            'responsable',
            'transiciones.usuario',
            'evidencias',
            'tareas.responsable',
        ]);

        $requisito = $implantacion->requisito;

        return Inertia::render('implantaciones/Ficha', [
            'implantacion' => [
                'id' => $implantacion->id,
                'aplica' => $implantacion->aplica,
                'justificacion' => $implantacion->justificacion,
                'estado' => $implantacion->estado->value,
                'estadoEtiqueta' => $implantacion->estado->etiqueta(),
                'nivel_madurez' => $implantacion->nivel_madurez?->value,
                'responsable_id' => $implantacion->responsable_id,
                'fecha_objetivo' => $implantacion->fecha_objetivo?->toDateString(),
                'notas' => $implantacion->notas,
            ],
            'requisito' => [
                'codigo' => $requisito->codigo,
                'titulo' => $requisito->titulo,
                'descripcion' => $requisito->descripcion,
                'marco' => $requisito->marco?->nombre,
                'atributos' => $requisito->atributos,
                // La rama del catálogo hasta este requisito, por CTE recursiva.
                // `op.acc.4` no se entiende sin saber que cuelga de `op.acc`.
                'ruta' => Requisito::ruta($requisito->id)
                    ->map(fn (Requisito $paso): array => [
                        'codigo' => $paso->codigo,
                        'titulo' => $paso->titulo,
                    ])
                    ->all(),
            ],
            'sistema' => [
                'id' => $implantacion->sistema->id,
                'codigo' => $implantacion->sistema->codigo,
                'nombre' => $implantacion->sistema->nombre,
                'categoria' => $implantacion->sistema->categoria()?->etiqueta(),
            ],
            // Por qué se le exige esta medida a este sistema. Sin esto, la
            // respuesta obliga a releer a mano la matriz del Anexo II.
            'exigencia' => [
                'valor' => $implantacion->exigencia_calculada?->valor,
                'etiqueta' => $implantacion->exigencia_calculada?->etiqueta(),
                'origen' => $implantacion->origen_exigencia?->etiqueta(),
                'dimension' => $implantacion->dimension_moduladora?->nombre(),
                'excluibleAMano' => $aplicabilidad->esExcluibleAMano($implantacion),
            ],
            /*
             * Aquí el cambio de estado es un formulario —desplegable, nota y
             * enviar— y no cuatro botones como en una tarea, así que las
             * opciones van con lo que un `Opcion` lleva y nada más. El badge del
             * estado actual sí gana su icono, por el respaldo de `lib/tonos.ts`.
             */
            'transicionesPermitidas' => array_map(
                static fn (EstadoImplantacion $estado): array => [
                    'valor' => $estado->value,
                    'etiqueta' => $estado->etiqueta(),
                ],
                $implantacion->estado->transicionesPermitidas(),
            ),
            'historico' => $implantacion->transiciones
                ->map(fn (ImplantacionTransicion $transicion): array => [
                    'id' => $transicion->id,
                    'anterior' => $transicion->estado_anterior?->etiqueta(),
                    'nuevo' => $transicion->estado_nuevo->etiqueta(),
                    'claveNuevo' => $transicion->estado_nuevo->value,
                    // Nulo cuando la transición la provocó el recálculo y no una
                    // persona, y la ficha lo dice en vez de dejar el hueco.
                    'usuario' => $transicion->usuario?->name,
                    'nota' => $transicion->nota,
                    'fecha' => $transicion->created_at?->toIso8601String(),
                ])
                ->all(),
            'evidencias' => $implantacion->evidencias
                ->map(fn (Evidencia $evidencia): array => [
                    'id' => $evidencia->id,
                    'titulo' => $evidencia->titulo,
                    'tipo' => $evidencia->tipo->etiqueta(),
                    'esFichero' => $evidencia->esFichero(),
                    'fecha_obtencion' => $evidencia->fecha_obtencion->toDateString(),
                    'fecha_caducidad' => $evidencia->fecha_caducidad?->toDateString(),
                    'haCaducado' => $evidencia->haCaducado(),
                    'nota' => $evidencia->getRelationValue('pivot')?->getAttribute('nota'),
                ])
                ->all(),
            /*
             * Lo que se está haciendo para cumplirlo. Va en la ficha del
             * requisito porque es ahí donde alguien se pregunta qué falta, igual
             * que las evidencias están donde alguien se pregunta cómo lo prueba.
             */
            'tareas' => $implantacion->tareas
                ->map(fn (Tarea $tarea): array => [
                    'id' => $tarea->id,
                    'titulo' => $tarea->titulo,
                    'estado' => $tarea->estado->value,
                    'estadoEtiqueta' => $tarea->estado->etiqueta(),
                    'tono' => $tarea->estado->tono(),
                    'prioridad' => $tarea->prioridad->etiqueta(),
                    'responsable' => $tarea->responsable?->name,
                    'fecha_limite' => $tarea->fecha_limite?->toDateString(),
                    'haVencido' => $tarea->haVencido(),
                ])
                ->all(),

            // Sólo viaja cuando el desplegable de vincular se abre: la ficha no
            // tiene por qué cargar el repositorio entero para enseñar tres
            // evidencias. `Inertia::optional()` es el sustituto de `lazy()`.
            'evidenciasDisponibles' => Inertia::optional(fn (): array => Evidencia::query()
                ->whereDoesntHave('implantaciones', fn (Builder $consulta) => $consulta
                    ->where('implantaciones.id', $implantacion->id))
                ->orderByDesc('fecha_obtencion')
                ->limit(100)
                ->get()
                ->map(fn (Evidencia $evidencia): array => [
                    'valor' => (string) $evidencia->id,
                    'etiqueta' => $evidencia->titulo,
                ])
                ->all()),
            'correspondencias' => $correspondencias->paraRequisito($requisito->id),
            'responsables' => User::query()
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): array => [
                    'valor' => (string) $usuario->id,
                    'etiqueta' => $usuario->name,
                ])
                ->all(),
            'niveles' => array_map(
                static fn (NivelMadurez $nivel): array => [
                    'valor' => $nivel->value,
                    'etiqueta' => $nivel->etiqueta(),
                ],
                NivelMadurez::cases(),
            ),
        ]);
    }

    /**
     * Los campos de gestión, y la aplicabilidad.
     *
     * `estado` no se escribe aquí: excluir o reincorporar mueve `aplica` y
     * `estado` a la vez y deja su fila en el histórico, y eso lo hace el
     * dominio, no un `update` de formulario.
     */
    public function update(
        GuardarImplantacionRequest $request,
        Implantacion $implantacion,
        CambiarAplicabilidad $aplicabilidad,
    ): RedirectResponse {
        $aplica = $request->boolean('aplica');
        $codigo = $implantacion->requisito->codigo;
        $manual = $aplicabilidad->esExcluibleAMano($implantacion);

        try {
            if ($aplica !== $implantacion->aplica) {
                $aplica
                    ? $aplicabilidad->incluir($implantacion, $request->user())
                    : $aplicabilidad->excluir(
                        $implantacion,
                        $request->string('justificacion')->toString(),
                        $request->user(),
                    );
            }
        } catch (ExclusionNoPermitida $e) {
            return back()->withErrors(['aplica' => $e->getMessage()])->withInput();
        }

        $implantacion->update($request->safe()->only([
            'responsable_id',
            'fecha_objetivo',
            'nivel_madurez',
            'notas',
        ]));

        // Corregir el texto de una exclusión que ya estaba puesta. Sólo en las
        // que se excluyen a mano: la justificación de una que dejó de exigirse
        // al recalcular la escribe el motor, y sobrescribirla con lo que traiga
        // un formulario que ni siquiera enseña el campo borraría la explicación
        // —y la base, que exige justificación cuando no aplica, rechazaría el
        // hueco con un error que no dice nada de la causa.
        if ($manual && ! $aplica) {
            $implantacion->update(['justificacion' => $request->string('justificacion')->toString()]);
        }

        Inertia::flash('exito', "Implantación de {$codigo} actualizada.");

        return back();
    }

    /**
     * Cambio de estado desde la ficha.
     *
     * Mismo camino que la acción masiva —`CambiarEstado`, con su validación de
     * la máquina de estados y su fila de histórico—, pero aquí una transición
     * rechazada es un error del formulario y no un recuento.
     */
    public function transicion(
        CambiarEstadoImplantacionRequest $request,
        Implantacion $implantacion,
        CambiarEstado $cambiarEstado,
    ): RedirectResponse {
        $nuevo = EstadoImplantacion::from($request->string('estado')->toString());

        try {
            $cambiarEstado(
                $implantacion,
                $nuevo,
                $request->user(),
                $request->string('nota')->toString() ?: null,
            );
        } catch (TransicionNoPermitida $e) {
            return back()->withErrors(['estado' => $e->getMessage()]);
        }

        Inertia::flash('exito', "Estado cambiado a {$nuevo->etiqueta()}.");

        return back();
    }

    /**
     * Adjunta una prueba a este requisito.
     *
     * La misma evidencia puede estar vinculada a un control de ISO y a tres
     * medidas del ENS: eso es el invariante 6, y es la razón por la que aquí no
     * se sube un fichero por requisito.
     */
    public function vincularEvidencia(
        VincularEvidenciaRequest $request,
        Implantacion $implantacion,
        VincularEvidencia $vincular,
    ): RedirectResponse {
        // El scope de organización decide: un identificador de otra
        // organización no aparece y esto responde 404.
        $evidencia = Evidencia::query()->findOrFail($request->integer('evidencia_id'));

        $vincular->vincular(
            $evidencia,
            $implantacion,
            $request->user(),
            $request->string('nota')->toString() ?: null,
        );

        Inertia::flash('exito', "«{$evidencia->titulo}» ya prueba este requisito.");

        return back();
    }

    public function desvincularEvidencia(
        Implantacion $implantacion,
        Evidencia $evidencia,
        VincularEvidencia $vincular,
    ): RedirectResponse {
        $vincular->desvincular($evidencia, $implantacion);

        Inertia::flash('exito', "«{$evidencia->titulo}» ya no prueba este requisito. La evidencia sigue en el repositorio.");

        return back();
    }

    /**
     * Cambio de estado en bloque.
     *
     * Las que no admiten la transición no se tocan y se cuentan aparte: fallar
     * la operación entera porque una fila de doscientas no encajaba obliga al
     * usuario a adivinar cuál era.
     */
    public function cambiarEstado(
        CambiarEstadoImplantacionesRequest $request,
        CambiarEstado $cambiarEstado,
    ): RedirectResponse {
        $nuevo = EstadoImplantacion::from($request->string('estado')->toString());
        $nota = $request->string('nota')->toString() ?: null;

        // El scope global filtra por organización: los identificadores de otra
        // organización simplemente no aparecen.
        $implantaciones = Implantacion::query()
            ->whereIn('id', $request->array('implantaciones'))
            ->get();

        $cambiadas = 0;
        $rechazadas = 0;

        foreach ($implantaciones as $implantacion) {
            try {
                $cambiarEstado($implantacion, $nuevo, $request->user(), $nota);
                $cambiadas++;
            } catch (TransicionNoPermitida) {
                $rechazadas++;
            }
        }

        $mensaje = trans_choice(
            '{0}Ninguna implantación cambió de estado.|{1}1 implantación pasó a :estado.|[2,*]:count implantaciones pasaron a :estado.',
            $cambiadas,
            ['estado' => mb_strtolower($nuevo->etiqueta())],
        );

        if ($rechazadas > 0) {
            $mensaje .= " {$rechazadas} no admitían esa transición y se quedaron como estaban.";
        }

        Inertia::flash($cambiadas > 0 ? 'exito' : 'error', $mensaje);

        return back();
    }
}
