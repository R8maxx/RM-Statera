<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Persona\AsignarSuperior;
use App\Domain\Persona\CodigoPuesto;
use App\Domain\Persona\Excepciones\PuestoCiclico;
use App\Domain\Persona\Models\AsignacionPuesto;
use App\Domain\Persona\Models\Puesto;
use App\Domain\Persona\Organigrama;
use App\Http\Requests\GuardarPuestoRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\PuestoRecurso;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los puestos y el organigrama: § 4.8 y la caracterización de `mp.per.1`.
 *
 * **Sin verbo de permiso propio.** Se reutilizan `personas.ver` y
 * `personas.gestionar`: es el mismo módulo, y un `puestos.*` nuevo habría que
 * acordarse de añadirlo a mano en las listas literales de `Rol::permisos()` para
 * `Tecnico` y `Auditor` — que es justo la trampa que `RolesTest` existe para
 * cazar y que este módulo no necesita correr.
 */
class PuestoController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, PuestoRecurso $recurso): Response
    {
        return Inertia::render('puestos/Index', [
            ...$this->tabla($recurso, $request),
            'total' => Puesto::query()->count(),
            'sinCaracterizar' => Puesto::query()->sinCaracterizar()->count(),
            'vacantes' => Puesto::query()->vacantes()->count(),
            'puedeGestionar' => $this->puede($request, Permiso::PersonasGestionar),
        ]);
    }

    /**
     * El organigrama: **pantalla propia y no un bloque de la tabla**.
     *
     * Es una ruta y no un conmutador de cliente, que es la decisión ya tomada
     * para `/tareas/tablero` y `/activos/etiquetas`: «un conmutador que recuerda
     * la última vista hace que el enlace que alguien pega en un correo abra otra
     * pantalla».
     */
    public function organigrama(Request $request, Organigrama $organigrama): Response
    {
        /*
         * `Puesto::hydrate()` devuelve una `Support\Collection`, que no tiene
         * `load()`. Se cargan las relaciones sobre una `Eloquent\Collection`
         * construida a mano: es lo que evita una consulta por nodo.
         */
        $arbol = new EloquentCollection($organigrama->arbol()->all());
        $arbol->load(['asignaciones' => static function (Relation $consulta): void {
            $consulta->whereNull('hasta')->with('persona');
        }]);

        return Inertia::render('puestos/Organigrama', [
            'nodos' => $arbol->map(fn (Puesto $puesto): array => [
                'id' => $puesto->id,
                'codigo' => $puesto->codigo,
                'titulo' => $puesto->titulo,
                'profundidad' => (int) $puesto->getAttribute('profundidad'),
                'caracterizado' => $puesto->estaCaracterizado(),
                'ocupantes' => $puesto->asignaciones
                    ->map(fn (AsignacionPuesto $asignacion): array => [
                        'id' => $asignacion->persona_id,
                        'nombre' => $asignacion->persona->nombre,
                    ])
                    ->values()
                    ->all(),
            ])->values()->all(),
            'sueltos' => Puesto::query()->whereNull('reporta_a_id')->count(),
            'total' => Puesto::query()->count(),
            'puedeGestionar' => $this->puede($request, Permiso::PersonasGestionar),
        ]);
    }

    public function create(CodigoPuesto $codigo): Response
    {
        return Inertia::render('puestos/Formulario', [
            'puesto' => null,
            'sugerencia' => ['codigo' => $codigo->siguiente()],
            'superiores' => $this->superiores(null),
        ]);
    }

    public function store(GuardarPuestoRequest $request): RedirectResponse
    {
        $puesto = Puesto::query()->create($request->validated());

        Inertia::flash('exito', "«{$puesto->titulo}» está en el catálogo de puestos.");

        return to_route('puestos.show', $puesto);
    }

    public function show(Request $request, Puesto $puesto): Response
    {
        $puesto->load([
            'reportaA',
            'dependientes',
            'asignaciones.persona',
            'asignaciones.asignadaPor',
        ]);

        return Inertia::render('puestos/Ficha', [
            'puesto' => $this->serializar($puesto),
            'dependientes' => $puesto->dependientes
                ->map(fn (Puesto $hijo): array => [
                    'id' => $hijo->id,
                    'codigo' => $hijo->codigo,
                    'titulo' => $hijo->titulo,
                ])->values()->all(),
            'asignaciones' => $puesto->asignaciones
                ->sortByDesc('desde')
                ->map(fn (AsignacionPuesto $asignacion): array => [
                    'id' => $asignacion->id,
                    'persona_id' => $asignacion->persona_id,
                    'persona' => $asignacion->persona->nombre,
                    'desde' => $asignacion->desde->toDateString(),
                    'hasta' => $asignacion->hasta?->toDateString(),
                    'vigente' => $asignacion->estaVigente(),
                    'nota' => $asignacion->nota,
                ])->values()->all(),
            'puedeGestionar' => $this->puede($request, Permiso::PersonasGestionar),
        ]);
    }

    public function edit(Puesto $puesto): Response
    {
        return Inertia::render('puestos/Formulario', [
            'puesto' => $this->serializar($puesto),
            'sugerencia' => null,
            'superiores' => $this->superiores($puesto),
        ]);
    }

    /**
     * Guardar y colocar en el organigrama son dos cosas.
     *
     * `reporta_a_id` no entra por asignación masiva: pasa por `AsignarSuperior`,
     * que es quien rechaza los ciclos. Si entrara por `update()`, un bucle de tres
     * saltos se escribiría sin que nadie lo mirara y la CTE del organigrama
     * dejaría de terminar.
     */
    public function update(GuardarPuestoRequest $request, Puesto $puesto, AsignarSuperior $asignar): RedirectResponse
    {
        $datos = $request->validated();
        $superiorId = $datos['reporta_a_id'] ?? null;
        unset($datos['reporta_a_id']);

        $puesto->update($datos);

        try {
            $asignar($puesto, $superiorId === null ? null : Puesto::query()->findOrFail($superiorId));
        } catch (PuestoCiclico $error) {
            return back()->withErrors(['reporta_a_id' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Puesto actualizado.');

        return to_route('puestos.show', $puesto);
    }

    public function destroy(Puesto $puesto): RedirectResponse
    {
        // Quien lo ocupó no se borra: la clave foránea es `restrictOnDelete` y el
        // error de la base no lo lee nadie, así que se dice aquí.
        if ($puesto->asignaciones()->exists()) {
            return back()->withErrors([
                'puesto' => 'Este puesto lo ha ocupado alguien, así que no se borra: primero hay que reasignar a esas personas.',
            ]);
        }

        $titulo = $puesto->titulo;
        $puesto->delete();

        Inertia::flash('exito', "«{$titulo}» ya no está en el catálogo de puestos.");

        return to_route('puestos.index');
    }

    /**
     * Los candidatos a superior.
     *
     * Se quita el propio puesto —el `CHECK` ya lo rechaza, pero ofrecerlo es
     * invitar a un error—, y **no se quitan sus descendientes**: la comprobación
     * de ciclos vive en el dominio y filtrarlos aquí sería la misma regla escrita
     * dos veces, con la de la interfaz quedándose corta el día que cambie.
     *
     * @return list<array{valor: string, etiqueta: string}>
     */
    private function superiores(?Puesto $puesto): array
    {
        return Puesto::query()
            ->when($puesto !== null, fn ($consulta) => $consulta->whereKeyNot($puesto->id))
            ->orderBy('titulo')
            ->get()
            ->map(fn (Puesto $uno): array => [
                'valor' => (string) $uno->id,
                'etiqueta' => $uno->titulo,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function serializar(Puesto $puesto): array
    {
        return [
            'id' => $puesto->id,
            'codigo' => $puesto->codigo,
            'titulo' => $puesto->titulo,
            'reporta_a_id' => $puesto->reporta_a_id,
            'reporta_a' => $puesto->reportaA?->titulo,
            'mision' => $puesto->mision,
            'funciones' => $puesto->funciones,
            'competencias' => $puesto->competencias,
            'caracterizado' => $puesto->estaCaracterizado(),
        ];
    }

    private function puede(Request $request, Permiso $permiso): bool
    {
        return $request->user()?->can($permiso->value) ?? false;
    }
}
