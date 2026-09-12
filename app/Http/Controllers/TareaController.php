<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Tarea\CambiarEstadoTarea;
use App\Domain\Tarea\CrearTarea;
use App\Domain\Tarea\Enums\EstadoTarea;
use App\Domain\Tarea\Enums\OrigenTarea;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Excepciones\TransicionDeTareaNoPermitida;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\Models\TareaTransicion;
use App\Domain\Tarea\VincularTarea;
use App\Http\Requests\CambiarEstadoTareaRequest;
use App\Http\Requests\CambiarEstadoTareasRequest;
use App\Http\Requests\GuardarTareaRequest;
use App\Http\Requests\VincularTareaRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\TareaRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function index(Request $request): Response
    {
        return Inertia::render('tareas/Index', $this->tabla(new TareaRecurso, $request));
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
