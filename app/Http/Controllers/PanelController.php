<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El estado del cumplimiento de un vistazo.
 *
 * Todas las consultas pasan por el scope de organización, así que lo que se
 * cuenta aquí es siempre lo de la organización activa.
 */
class PanelController extends Controller
{
    public function __invoke(): Response
    {
        $sistemas = Sistema::query()
            ->with('marco')
            ->withCount([
                'implantaciones as aplicables' => fn (Builder $query) => $query->where('aplica', true),
                'implantaciones as implantadas' => fn (Builder $query) => $query
                    ->where('estado', EstadoImplantacion::Implantado->value),
            ])
            ->orderBy('codigo')
            ->get()
            ->map(fn (Sistema $sistema): array => [
                'id' => $sistema->id,
                'codigo' => $sistema->codigo,
                'nombre' => $sistema->nombre,
                'marco' => $sistema->marco?->nombre,
                'categoria' => $sistema->categoria()?->etiqueta(),
                // Alias de `withCount`: no son columnas del modelo, así que se
                // leen por `getAttribute` y no como propiedad.
                'aplicables' => (int) $sistema->getAttribute('aplicables'),
                'implantadas' => (int) $sistema->getAttribute('implantadas'),
            ])
            ->values();

        return Inertia::render('Panel', [
            'sistemas' => $sistemas,
            'resumen' => [
                'sistemas' => $sistemas->count(),
                'aplicables' => (int) $sistemas->sum('aplicables'),
                'implantadas' => (int) $sistemas->sum('implantadas'),
                'pendientes' => Implantacion::query()
                    ->where('aplica', true)
                    ->whereNot('estado', EstadoImplantacion::Implantado->value)
                    ->count(),
            ],
        ]);
    }
}
