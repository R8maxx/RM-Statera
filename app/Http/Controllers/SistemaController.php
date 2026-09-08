<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalogo\Models\Marco;
use App\Domain\Sistema\Enums\EstadoSistema;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Requests\GuardarSistemaRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\SistemaRecurso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Delgado a propósito: valida con el `FormRequest`, delega y devuelve Inertia.
 *
 * El aislamiento lo pone el modelo (`PerteneceAOrganizacion`), no este
 * controlador: un `findOrFail` de un sistema de otra organización devuelve 404
 * porque el scope global lo deja fuera de la consulta, que es exactamente lo que
 * debe pasar.
 */
class SistemaController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request): Response
    {
        return Inertia::render('sistemas/Index', $this->tabla(new SistemaRecurso, $request));
    }

    public function create(): Response
    {
        return Inertia::render('sistemas/Formulario', [
            'sistema' => null,
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarSistemaRequest $request): RedirectResponse
    {
        $sistema = Sistema::query()->create($request->validated());

        Inertia::flash('exito', "Sistema {$sistema->codigo} creado.");

        return to_route('sistemas.index');
    }

    public function edit(Sistema $sistema): Response
    {
        return Inertia::render('sistemas/Formulario', [
            'sistema' => [
                'id' => $sistema->id,
                'codigo' => $sistema->codigo,
                'nombre' => $sistema->nombre,
                'marco_id' => $sistema->marco_id,
                'descripcion' => $sistema->descripcion,
                'estado' => $sistema->estado->value,
                'alcance_declarado' => $sistema->alcance_declarado,
                'exclusiones_justificadas' => $sistema->exclusiones_justificadas,
            ],
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarSistemaRequest $request, Sistema $sistema): RedirectResponse
    {
        $sistema->update($request->validated());

        Inertia::flash('exito', "Sistema {$sistema->codigo} actualizado.");

        return to_route('sistemas.index');
    }

    public function destroy(Sistema $sistema): RedirectResponse
    {
        $codigo = $sistema->codigo;
        $sistema->delete();

        Inertia::flash('exito', "Sistema {$codigo} eliminado.");

        return to_route('sistemas.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'marcos' => Marco::query()
                ->orderBy('nombre')
                ->get()
                ->map(fn (Marco $marco): array => [
                    'valor' => (string) $marco->id,
                    'etiqueta' => $marco->nombre,
                ])
                ->all(),
            'estados' => array_map(
                static fn (EstadoSistema $estado): array => [
                    'valor' => $estado->value,
                    'etiqueta' => $estado->etiqueta(),
                ],
                EstadoSistema::cases(),
            ),
        ];
    }
}
