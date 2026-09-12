<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Aviso\CalendarioVencimientos;
use App\Domain\Aviso\RejillaMes;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Tarea\CambiarEstadoTarea;
use App\Domain\Tarea\CrearTarea;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Excepciones\TransicionDeTareaNoPermitida;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\Models\TareaTransicion;
use App\Domain\Tarea\Plazo;
use App\Domain\Tarea\ResumenPlanDeAccion;
use App\Domain\Tarea\VincularTarea;
use App\Http\Requests\CambiarEstadoTareaRequest;
use App\Http\Requests\CambiarEstadoTareasRequest;
use App\Http\Requests\GuardarTareaRequest;
use App\Http\Requests\VincularTareaRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\TareaRecurso;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El plan de acción.
 *
 * Delgado como el resto: valida con el `FormRequest`, delega en el dominio y
 * devuelve Inertia. El estado no se cambia por el formulario —tiene su propia
 * ruta, que es la que registra la transición y ajusta la fecha de cierre—.
 */
class TareaController extends Controller
{
    use RespondeConRecurso;

    /**
     * Las columnas del tablero, en el orden en que avanza el trabajo.
     *
     * @var list<EstadoTarea>
     */
    private const COLUMNAS = [
        EstadoTarea::Pendiente,
        EstadoTarea::EnCurso,
        EstadoTarea::Bloqueada,
        EstadoTarea::Hecha,
    ];

    /** Cuántas tarjetas caben en una columna antes de resumir el resto. */
    private const POR_COLUMNA = 50;

    /** Cuánto tiempo sigue viéndose en el tablero algo ya cerrado. */
    private const DIAS_HECHAS = 14;

    public function index(Request $request, ResumenPlanDeAccion $resumen): Response
    {
        return Inertia::render('tareas/Index', [
            ...$this->tabla(new TareaRecurso, $request),
            // No se recalculan al paginar ni al ordenar, pero sí al filtrar por
            // uno de ellos —cambian cuando alguien cierra algo—, así que viajan
            // como prop normal y no como `once`.
            'alertas' => $resumen->alertas(),
            'pendientes' => $resumen->pendientesDeCompletar(),
            'abiertas' => Tarea::query()->abiertas()->count(),
        ]);
    }

    /**
     * El tablero: en qué punto está cada cosa.
     *
     * **Cuatro columnas y no cinco.** `descartada` no tiene columna porque
     * descartar exige un motivo y eso no cabe en un gesto de arrastre, y porque
     * una columna de descartadas crece para siempre y no se mira nunca. Lo
     * descartado se ve en la tabla, que es donde se busca.
     *
     * En «Hecha» sólo entra lo cerrado **hace poco**: el tablero enseña el
     * trabajo en curso, y una columna con las trescientas tareas cerradas desde
     * enero deja de decir nada. Lo demás está en la tabla.
     *
     * Cada columna trae un tope y su cuenta real, para poder decir «y N más» con
     * enlace a la lista en vez de meter quinientas tarjetas en el DOM.
     */
    public function tablero(): Response
    {
        $columnas = [];

        foreach (self::COLUMNAS as $estado) {
            $consulta = Tarea::query()
                ->where('estado', $estado->value)
                ->when(
                    $estado === EstadoTarea::Hecha,
                    fn (Builder $q) => $q->whereDate('fecha_cierre', '>=', Carbon::today()->subDays(self::DIAS_HECHAS)),
                )
                ->with('responsable')
                ->withCount('implantaciones as requisitos_count')
                ->orderByRaw('fecha_limite NULLS LAST')
                ->orderBy('id');

            $total = (clone $consulta)->count();

            $columnas[] = [
                'estado' => $estado->value,
                'etiqueta' => $estado->etiqueta(),
                'tono' => $estado->tono(),
                'total' => $total,
                'ocultas' => max($total - self::POR_COLUMNA, 0),
                'filtro' => "filter[estado]={$estado->value}",
                'tarjetas' => $consulta->limit(self::POR_COLUMNA)->get()
                    ->map(fn (Tarea $tarea): array => $this->tarjeta($tarea))
                    ->all(),
            ];
        }

        return Inertia::render('tareas/Tablero', [
            'columnas' => $columnas,
            'recientes' => self::DIAS_HECHAS,
        ]);
    }

    /**
     * Una tarjeta del tablero.
     *
     * `transiciones` viaja con cada tarjeta **a propósito**: es lo que permite
     * que el cliente sepa a qué columnas puede soltarse sin preguntar al
     * servidor, y por tanto que una columna prohibida se marque como tal durante
     * el arrastre en vez de aceptar el soltado y fallar después. El servidor
     * vuelve a comprobarlo igual —`CambiarEstadoTarea` es quien manda—: esto es
     * para que el gesto no mienta, no para confiar en el navegador.
     *
     * @return array<string, mixed>
     */
    private function tarjeta(Tarea $tarea): array
    {
        $plazo = Plazo::de($tarea);

        return [
            'id' => $tarea->id,
            'titulo' => $tarea->titulo,
            'estado' => $tarea->estado->value,
            'prioridad' => $tarea->prioridad->value,
            'prioridadEtiqueta' => $tarea->prioridad->etiqueta(),
            'prioridadTono' => $tarea->prioridad->tono(),
            'responsable' => $tarea->responsable?->name,
            'plazo' => ['etiqueta' => $plazo->etiqueta, 'tono' => $plazo->tono, 'fecha' => $plazo->fecha],
            'requisitos' => (int) $tarea->getAttribute('requisitos_count'),
            'transiciones' => array_values(array_filter(
                array_map(
                    static fn (EstadoTarea $estado): string => $estado->value,
                    $tarea->estado->transicionesPermitidas(),
                ),
                static fn (string $estado): bool => in_array(
                    $estado,
                    array_map(static fn (EstadoTarea $columna): string => $columna->value, self::COLUMNAS),
                    true,
                ),
            )),
            // Descartar no es una columna, pero sigue siendo una salida: la
            // tarjeta la ofrece y el diálogo pide el motivo.
            'descartable' => $tarea->estado->permite(EstadoTarea::Descartada),
        ];
    }

    /**
     * El calendario: qué cae esta semana.
     *
     * **Enseña vencimientos, no tareas.** Una tarea que vence y una evidencia
     * que caduca son la misma pregunta para quien mira el mes —«¿qué tengo que
     * atender?»— y separarlas en dos calendarios obliga a mirar dos. § 4.16
     * —calendario de obligaciones— incluye literalmente la caducidad de
     * evidencias, así que esto es su primera pieza: cuando lleguen la revisión
     * por la dirección o la auditoría interna, se cuelgan de `Fuente` y esta
     * pantalla no se entera.
     *
     * El mes vive en la URL para que se pueda enlazar y compartir, y lo que no
     * se entienda es el mes de hoy: un 500 en una dirección que alguien guarda
     * es peor que enseñar otro mes.
     */
    public function calendario(Request $request, CalendarioVencimientos $calendario): Response
    {
        $rejilla = RejillaMes::de($request->string('mes')->toString());

        return Inertia::render('tareas/Calendario', [
            'rejilla' => $rejilla,
            // Se consulta por los extremos de la REJILLA y no por los del mes:
            // las casillas de relleno son días de verdad y lo que caiga en ellas
            // también hay que atenderlo.
            'vencimientos' => $calendario->entre(
                Carbon::parse($rejilla->primerDia),
                Carbon::parse($rejilla->ultimoDia),
            ),
        ]);
    }

    public function create(Request $request): Response
    {
        // Puede venir de la ficha de un requisito: entonces la tarea nace ya
        // vinculada y con el origen puesto.
        $implantacion = $request->integer('implantacion') ?: null;

        return Inertia::render('tareas/Formulario', [
            'tarea' => null,
            'desdeImplantacion' => $implantacion === null
                ? null
                : $this->requisito(Implantacion::query()->findOrFail($implantacion)),
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarTareaRequest $request, CrearTarea $crear): RedirectResponse
    {
        $implantaciones = Implantacion::query()
            ->whereIn('id', $request->input('implantaciones', []))
            ->get()
            ->all();

        $tarea = $crear(
            $request->safe()->except('implantaciones'),
            $request->user(),
            $implantaciones,
        );

        Inertia::flash('exito', $implantaciones === []
            ? "Tarea «{$tarea->titulo}» creada."
            : "Tarea «{$tarea->titulo}» creada y vinculada a ".count($implantaciones).' requisito(s).');

        return to_route('tareas.show', $tarea);
    }

    /**
     * La ficha: qué hace avanzar esta tarea y qué le ha pasado.
     */
    public function show(Tarea $tarea): Response
    {
        $tarea->load([
            'responsable',
            'implantaciones.requisito.marco',
            'implantaciones.sistema',
            'transiciones.usuario',
        ]);

        return Inertia::render('tareas/Ficha', [
            'tarea' => $this->serializar($tarea),
            'vinculos' => $tarea->implantaciones
                ->map(fn (Implantacion $implantacion): array => $this->requisito($implantacion))
                ->all(),
            'historico' => $tarea->transiciones
                ->map(fn (TareaTransicion $transicion): array => [
                    'id' => $transicion->id,
                    'anterior' => $transicion->estado_anterior?->etiqueta(),
                    'nuevo' => $transicion->estado_nuevo->etiqueta(),
                    'tono' => $transicion->estado_nuevo->tono(),
                    'quien' => $transicion->usuario?->name,
                    'cuando' => $transicion->created_at->toIso8601String(),
                    'nota' => $transicion->nota,
                ])
                ->all(),
            'transiciones' => array_map(
                static fn (EstadoTarea $estado): array => [
                    'valor' => $estado->value,
                    'etiqueta' => $estado->etiqueta(),
                ],
                $tarea->estado->transicionesPermitidas(),
            ),
        ]);
    }

    public function edit(Tarea $tarea): Response
    {
        return Inertia::render('tareas/Formulario', [
            'tarea' => $this->serializar($tarea),
            'desdeImplantacion' => null,
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarTareaRequest $request, Tarea $tarea): RedirectResponse
    {
        $tarea->update($request->safe()->except('implantaciones'));

        Inertia::flash('exito', "Tarea «{$tarea->titulo}» actualizada.");

        return to_route('tareas.show', $tarea);
    }

    public function destroy(Tarea $tarea): RedirectResponse
    {
        $titulo = $tarea->titulo;

        $tarea->delete();

        Inertia::flash('exito', "Tarea «{$titulo}» eliminada.");

        return to_route('tareas.index');
    }

    public function transicion(
        CambiarEstadoTareaRequest $request,
        Tarea $tarea,
        CambiarEstadoTarea $cambiar,
    ): RedirectResponse {
        $estado = EstadoTarea::from($request->string('estado')->toString());

        try {
            $cambiar($tarea, $estado, $request->user(), $request->string('nota')->value() ?: null);
        } catch (TransicionDeTareaNoPermitida $excepcion) {
            return back()->withErrors(['estado' => $excepcion->getMessage()]);
        }

        Inertia::flash('exito', "Tarea «{$tarea->titulo}»: {$estado->etiqueta()}.");

        return back();
    }

    /**
     * Acción masiva desde la tabla.
     *
     * Las que no admiten la transición se saltan y se cuentan: parar la tanda
     * entera porque tres de cincuenta ya estaban hechas obliga a quitarlas de la
     * selección a mano y volver a empezar.
     */
    public function estado(CambiarEstadoTareasRequest $request, CambiarEstadoTarea $cambiar): RedirectResponse
    {
        $estado = EstadoTarea::from($request->string('estado')->toString());
        $nota = $request->string('nota')->value() ?: null;

        $cambiadas = 0;
        $saltadas = 0;

        foreach (Tarea::query()->whereIn('id', $request->input('tareas', []))->get() as $tarea) {
            try {
                $cambiar($tarea, $estado, $request->user(), $nota);
                $cambiadas++;
            } catch (TransicionDeTareaNoPermitida) {
                $saltadas++;
            }
        }

        Inertia::flash('exito', $saltadas === 0
            ? "{$cambiadas} tarea(s) a «{$estado->etiqueta()}»."
            : "{$cambiadas} tarea(s) a «{$estado->etiqueta()}». {$saltadas} no admitían ese cambio y se han dejado como estaban.");

        return back();
    }

    public function vincular(VincularTareaRequest $request, Tarea $tarea, VincularTarea $vinculos): RedirectResponse
    {
        $implantacion = Implantacion::query()->findOrFail($request->integer('implantacion_id'));

        $vinculos->vincular($tarea, $implantacion, $request->user());

        Inertia::flash('exito', "Tarea vinculada a {$implantacion->requisito->codigo}.");

        return back();
    }

    public function desvincular(Tarea $tarea, Implantacion $implantacion, VincularTarea $vinculos): RedirectResponse
    {
        $vinculos->desvincular($tarea, $implantacion);

        Inertia::flash('exito', 'Vínculo eliminado.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function requisito(Implantacion $implantacion): array
    {
        $implantacion->loadMissing(['requisito.marco', 'sistema']);

        return [
            'implantacionId' => $implantacion->id,
            'codigo' => $implantacion->requisito->codigo,
            'titulo' => $implantacion->requisito->titulo,
            'marco' => $implantacion->requisito->marco?->codigo,
            'sistema' => $implantacion->sistema->codigo,
            'estado' => $implantacion->estado->value,
            'estadoEtiqueta' => $implantacion->estado->etiqueta(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Tarea $tarea): array
    {
        return [
            'id' => $tarea->id,
            'titulo' => $tarea->titulo,
            'descripcion' => $tarea->descripcion,
            'origen' => $tarea->origen->value,
            'origenEtiqueta' => $tarea->origen->etiqueta(),
            'estado' => $tarea->estado->value,
            'estadoEtiqueta' => $tarea->estado->etiqueta(),
            'estadoTono' => $tarea->estado->tono(),
            'prioridad' => $tarea->prioridad->value,
            'prioridadEtiqueta' => $tarea->prioridad->etiqueta(),
            'responsable_id' => $tarea->responsable_id,
            'responsable' => $tarea->responsable?->name,
            'fecha_limite' => $tarea->fecha_limite?->toDateString(),
            'fecha_cierre' => $tarea->fecha_cierre?->toDateString(),
            'haVencido' => $tarea->haVencido(),
            'coste_estimado' => $tarea->coste_estimado,
            'notas' => $tarea->notas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'origenes' => array_map(
                static fn (OrigenTarea $origen): array => [
                    'valor' => $origen->value,
                    'etiqueta' => $origen->etiqueta(),
                ],
                OrigenTarea::disponibles(),
            ),
            'prioridades' => array_map(
                static fn (PrioridadTarea $prioridad): array => [
                    'valor' => $prioridad->value,
                    'etiqueta' => $prioridad->etiqueta(),
                ],
                PrioridadTarea::cases(),
            ),
            'responsables' => User::query()
                ->orderBy('name')
                ->get()
                ->map(static fn (User $usuario): array => [
                    'valor' => (string) $usuario->id,
                    'etiqueta' => $usuario->name,
                ])
                ->all(),
        ];
    }
}
