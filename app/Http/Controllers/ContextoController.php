<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Contexto\AnalisisEnCurso;
use App\Domain\Contexto\AprobarAnalisis;
use App\Domain\Contexto\ComparativaAnalisis;
use App\Domain\Contexto\Enums\Ambito;
use App\Domain\Contexto\Enums\TipoCuestion;
use App\Domain\Contexto\Excepciones\AnalisisNoAprobable;
use App\Domain\Contexto\Models\AnalisisContexto;
use App\Domain\Contexto\Models\CuestionContexto;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Contexto\RegistroContexto;
use App\Domain\Sistema\Enums\EstadoSistema;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Requests\GuardarAnalisisContextoRequest;
use App\Http\Resources\AnalisisContextoRecurso;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El panorama del contexto: § 4.1, cláusulas 4.1 a 4.3 de ISO.
 *
 * `/contexto` es **la vista de lectura** —la matriz del DAFO, la declaración del
 * cambio climático y el alcance de cada sistema— y `/contexto/cuestiones` la tabla
 * donde se trabaja. Es el mismo reparto que `/tareas` y `/tareas/tablero`, con la
 * ruta corta en la vista que se mira y no en la que se edita, porque aquí lo que
 * se consulta a diario es la matriz.
 *
 * **El alcance se lee de `sistemas` y no se edita aquí.** La cláusula 4.3 ya vivía
 * en `sistemas.alcance_declarado` desde la primera migración, con su formulario y
 * su sitio en la portada de los cuatro documentos. Repetir el campo aquí sería el
 * mismo dato en dos pantallas que pueden discrepar; lo que este módulo le añade es
 * histórico, congelándolo en la instantánea al aprobar.
 */
class ContextoController extends Controller
{
    use RespondeConRecurso;

    public function index(AnalisisEnCurso $enCurso, RegistroContexto $registro): Response
    {
        $vigente = $enCurso->vigente();
        $borrador = $enCurso->borrador();

        return Inertia::render('contexto/Index', [
            'vigente' => $vigente === null ? null : $this->serializarAnalisis($vigente),
            'borrador' => $borrador === null ? null : $this->serializarAnalisis($borrador),
            'dafo' => $this->dafo(),
            'ejes' => $this->ejes(),
            'alcance' => $this->alcance(),
            'resumen' => $registro->paraElPanel(),
            'puedeGestionar' => $this->puede(Permiso::ContextoGestionar),
            'puedeAprobar' => $this->puede(Permiso::ContextoAprobar),
        ]);
    }

    /** El historial de revisiones, que es lo que contesta a «¿qué ha cambiado?». */
    public function analisis(Request $request, AnalisisEnCurso $enCurso): Response
    {
        return Inertia::render('contexto/Analisis', [
            ...$this->tabla(new AnalisisContextoRecurso, $request),
            'hayBorrador' => $enCurso->borrador() instanceof AnalisisContexto,
        ]);
    }

    public function mostrarAnalisis(AnalisisContexto $analisis, ComparativaAnalisis $comparativa): Response
    {
        $analisis->load(['creadoPor', 'aprobadoPor']);
        $cambios = $comparativa->para($analisis);

        return Inertia::render('contexto/AnalisisFicha', [
            'analisis' => $this->serializarAnalisis($analisis),
            'instantanea' => $analisis->instantanea,
            'cambios' => [
                'cuestionesAltas' => array_map($this->resumirCuestion(...), $cambios['cuestionesAltas']),
                'cuestionesBajas' => array_map($this->resumirCuestion(...), $cambios['cuestionesBajas']),
                'partesAltas' => array_map($this->resumirParte(...), $cambios['partesAltas']),
                'partesBajas' => array_map($this->resumirParte(...), $cambios['partesBajas']),
            ],
            'puedeAprobar' => $this->puede(Permiso::ContextoAprobar),
        ]);
    }

    /**
     * Guarda la cabecera del borrador, estrenándolo si no había ninguno.
     *
     * Estrenarlo aquí y no sólo al registrar la primera cuestión es lo que permite
     * contestar a la pregunta del cambio climático antes de escribir nada del
     * DAFO, que es el orden en que mucha gente lo hace.
     */
    public function guardarAnalisis(GuardarAnalisisContextoRequest $request, AnalisisEnCurso $enCurso): RedirectResponse
    {
        $borrador = $enCurso->borradorObligatorio($request->user());
        $borrador->update($request->validated());

        Inertia::flash('exito', 'Análisis del contexto guardado.');

        return back();
    }

    public function aprobar(Request $request, AnalisisContexto $analisis, AprobarAnalisis $aprobar): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        try {
            $aprobado = $aprobar($analisis, $usuario);
        } catch (AnalisisNoAprobable $error) {
            /*
             * Sube como error de validación al lado del campo que lo causó, no
             * como un 500. El `CHECK` de la base dice lo mismo y su mensaje va sin
             * tildes porque es SQL: no lo lee nadie.
             */
            return back()->withErrors([$error->campo ?? 'estado' => $error->getMessage()]);
        }

        Inertia::flash('exito', "{$aprobado->etiqueta()} aprobado. A partir de ahora es el contexto vigente de la organización.");

        return to_route('contexto.analisis.show', $aprobado);
    }

    /**
     * Las cuestiones vigentes, agrupadas por cuadrante.
     *
     * **Sólo vigentes**, a diferencia de la tabla: la matriz es lo que la
     * organización sostiene hoy, y meterle las retiradas la convertiría en un
     * archivo. Quien quiera verlas tiene la tabla, que las enseña con su motivo.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function dafo(): array
    {
        $cuestiones = CuestionContexto::query()
            ->vigentes()
            ->with('responsable')
            ->withCount(['riesgos', 'tareas'])
            ->orderBy('codigo')
            ->get();

        $dafo = [];

        foreach (TipoCuestion::enOrdenDeMatriz() as $tipo) {
            $dafo[$tipo->value] = $cuestiones
                ->where('tipo', $tipo)
                ->map(fn (CuestionContexto $cuestion): array => [
                    ...$this->resumirCuestion($cuestion),
                    'riesgos' => $cuestion->riesgos_count,
                    'tareas' => $cuestion->tareas_count,
                ])
                ->values()
                ->all();
        }

        return $dafo;
    }

    /**
     * Los rótulos de los dos ejes, con su tipo dentro.
     *
     * Van desde el servidor porque el cliente no puede deducir qué cuadrante cae
     * en cada casilla: el ámbito y el signo los deriva `TipoCuestion`, y una
     * segunda copia de esa tabla en el `.vue` es cómo se acaba con una matriz que
     * pinta las amenazas donde van las debilidades.
     *
     * @return array<string, mixed>
     */
    private function ejes(): array
    {
        return [
            'ambitos' => array_map(static fn (Ambito $ambito): array => [
                'valor' => $ambito->value,
                'etiqueta' => $ambito->etiqueta(),
                'ayuda' => $ambito->ayuda(),
                'tipos' => array_map(static fn (TipoCuestion $tipo): array => [
                    'valor' => $tipo->value,
                    'etiqueta' => $tipo->etiqueta(),
                    'tono' => $tipo->tono(),
                    'icono' => $tipo->icono(),
                    'signo' => $tipo->signo()->value,
                    'signoEtiqueta' => $tipo->signo()->etiqueta(),
                ], TipoCuestion::deAmbito($ambito)),
            ], Ambito::cases()),
        ];
    }

    /**
     * La cláusula 4.3, leída de donde ya vivía.
     *
     * @return list<array<string, mixed>>
     */
    private function alcance(): array
    {
        return Sistema::query()
            ->where('estado', EstadoSistema::Activo)
            ->with('marco')
            ->orderBy('codigo')
            ->get()
            ->map(fn (Sistema $sistema): array => [
                'id' => $sistema->id,
                'codigo' => $sistema->codigo,
                'nombre' => $sistema->nombre,
                'marco' => $sistema->marco?->codigo,
                'alcanceDeclarado' => $sistema->alcance_declarado,
                'exclusiones' => $sistema->exclusiones_justificadas,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarAnalisis(AnalisisContexto $analisis): array
    {
        return [
            'id' => $analisis->id,
            'numero' => $analisis->numero,
            'etiqueta' => $analisis->etiqueta(),
            'fechaAnalisis' => $analisis->fecha_analisis->toDateString(),
            'estado' => [
                'valor' => $analisis->estado->value,
                'etiqueta' => $analisis->estado->etiqueta(),
                'tono' => $analisis->estado->tono(),
                'icono' => $analisis->estado->icono(),
            ],
            'climaPertinente' => $analisis->clima_pertinente,
            'climaJustificacion' => $analisis->clima_justificacion,
            'nota' => $analisis->nota,
            'creadoPor' => $analisis->creadoPor?->name,
            'aprobadoPor' => $analisis->aprobadoPor?->name,
            'aprobadoEn' => $analisis->aprobado_en?->toDateTimeString(),
            'tieneInstantanea' => $analisis->instantanea !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumirCuestion(CuestionContexto $cuestion): array
    {
        return [
            'id' => $cuestion->id,
            'codigo' => $cuestion->codigo,
            'titulo' => $cuestion->titulo,
            'descripcion' => $cuestion->descripcion,
            'tipo' => $cuestion->tipo->value,
            'tipoEtiqueta' => $cuestion->tipo->etiqueta(),
            'tono' => $cuestion->tipo->tono(),
            'icono' => $cuestion->tipo->icono(),
            'materia' => $cuestion->materia->etiqueta(),
            'esClimatica' => $cuestion->es_climatica,
            'responsable' => $cuestion->responsable?->name,
            'motivoBaja' => $cuestion->motivo_baja,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumirParte(ParteInteresada $parte): array
    {
        return [
            'id' => $parte->id,
            'codigo' => $parte->codigo,
            'nombre' => $parte->nombre,
            'tipo' => $parte->tipo->etiqueta(),
            'ambito' => $parte->ambito->etiqueta(),
            'motivoBaja' => $parte->motivo_baja,
        ];
    }

    private function puede(Permiso $permiso): bool
    {
        return request()->user()?->can($permiso->value) ?? false;
    }
}
