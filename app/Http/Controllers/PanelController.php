<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\ResumenCumplimiento;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Resources\Panel\ResumenPanel;
use App\Http\Resources\Panel\SistemaResumido;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El estado del cumplimiento de un vistazo.
 *
 * Todas las consultas pasan por el scope de organización, así que lo que se
 * cuenta aquí es siempre lo de la organización activa. Las cifras las calcula
 * `ResumenCumplimiento`, en el dominio: son las mismas preguntas que contestará
 * el informe de estado, y no pueden vivir en un controlador.
 */
class PanelController extends Controller
{
    public function __invoke(ResumenCumplimiento $resumen): Response
    {
        $sistemas = Sistema::query()
            ->with('marco')
            ->withCount([
                'implantaciones as aplicables' => fn (Builder $query) => $query->where('aplica', true),
                // Sólo cuentan las implantadas que además son exigibles: sin el
                // `aplica`, una medida excluida y luego implantada inflaba el
                // numerador por encima del denominador.
                'implantaciones as implantadas' => fn (Builder $query) => $query
                    ->where('aplica', true)
                    ->where('estado', EstadoImplantacion::Implantado->value),
            ])
            ->orderBy('codigo')
            ->get()
            ->map(fn (Sistema $sistema): SistemaResumido => new SistemaResumido(
                id: $sistema->id,
                codigo: $sistema->codigo,
                nombre: $sistema->nombre,
                marco: $sistema->marco?->nombre,
                categoria: $sistema->categoria()?->etiqueta(),
                // Alias de `withCount`: no son columnas del modelo, así que se
                // leen por `getAttribute` y no como propiedad.
                aplicables: (int) $sistema->getAttribute('aplicables'),
                implantadas: (int) $sistema->getAttribute('implantadas'),
            ))
            ->values();

        $madurez = $resumen->madurez();

        return Inertia::render('Panel', [
            'sistemas' => $sistemas,
            'resumen' => new ResumenPanel(
                sistemas: $sistemas->count(),
                aplicables: (int) $sistemas->sum(fn (SistemaResumido $sistema): int => $sistema->aplicables),
                implantadas: (int) $sistemas->sum(fn (SistemaResumido $sistema): int => $sistema->implantadas),
                pendientes: $resumen->pendientes(),
                madurezMedia: $madurez['media'],
                madurezEvaluadas: $madurez['evaluadas'],
            ),
            'porEstado' => $resumen->porEstado(),
            'porMarco' => $resumen->porMarco(),
        ]);
    }
}
