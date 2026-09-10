<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Activo\Models\RevisionInventario;
use App\Http\Requests\GuardarRevisionInventarioRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\RevisionInventarioRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El registro de revisiones del inventario.
 *
 * Delgado como el resto. Lo único que merece nota es que no hay ficha aparte:
 * una revisión es fecha, alcance y dos textos, y una pantalla propia para eso
 * sería un clic de más entre la lista y lo que se quiere leer. Se lee y se
 * edita en el mismo formulario.
 */
class RevisionInventarioController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, RevisionInventarioRecurso $recurso): Response
    {
        return Inertia::render('revisiones/Index', $this->tabla($recurso, $request));
    }

    public function create(): Response
    {
        return Inertia::render('revisiones/Formulario', [
            'revision' => null,
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarRevisionInventarioRequest $request): RedirectResponse
    {
        RevisionInventario::query()->create($request->validated());

        Inertia::flash('exito', 'Revisión registrada. El inventario consta como mantenido a esta fecha.');

        return to_route('revisiones.index');
    }

    public function edit(RevisionInventario $revision): Response
    {
        return Inertia::render('revisiones/Formulario', [
            'revision' => $this->serializar($revision),
            ...$this->opciones(),
        ]);
    }

    public function update(
        GuardarRevisionInventarioRequest $request,
        RevisionInventario $revision,
    ): RedirectResponse {
        $revision->update($request->validated());

        Inertia::flash('exito', 'Revisión actualizada.');

        return to_route('revisiones.index');
    }

    public function destroy(RevisionInventario $revision): RedirectResponse
    {
        $fecha = $revision->fecha->format('d/m/Y');
        $revision->delete();

        Inertia::flash('exito', "Revisión del {$fecha} eliminada.");

        return to_route('revisiones.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(RevisionInventario $revision): array
    {
        return [
            'id' => $revision->id,
            'fecha' => $revision->fecha->toDateString(),
            'responsable_id' => $revision->responsable_id,
            'alcance' => $revision->alcance,
            'altas' => $revision->altas,
            'bajas' => $revision->bajas,
            'desviaciones' => $revision->desviaciones,
            'acciones' => $revision->acciones,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'personas' => User::query()
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
