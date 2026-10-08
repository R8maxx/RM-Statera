<?php

declare(strict_types=1);

namespace App\Http\Controllers\Plataforma;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\EstadoExportacion;
use App\Domain\Plataforma\Exportacion\ExportarOrganizacion;
use App\Domain\Plataforma\Exportacion\SolicitarExportacion;
use App\Domain\Plataforma\Models\ExportacionOrganizacion;
use App\Domain\Plataforma\TrazaPlataforma;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportar todos los datos de un cliente y descargarlos (punto 56).
 *
 * La descarga va por su capacidad y queda en la traza: son todos los datos de
 * un cliente, y el auditor de nuestro SGSI preguntará quién se los llevó.
 */
class ExportacionController extends Controller
{
    public function store(Request $request, Organizacion $organizacion, SolicitarExportacion $solicitar): RedirectResponse
    {
        /** @var User $yo */
        $yo = $request->user();

        $solicitar($yo, $organizacion);

        Inertia::flash('exito', 'Exportación en preparación. Recarga la ficha en unos minutos para descargarla.');

        return to_route('plataforma.organizaciones.show', $organizacion);
    }

    public function descargar(ExportacionOrganizacion $exportacion, TrazaPlataforma $traza): StreamedResponse
    {
        abort_unless($exportacion->estado === EstadoExportacion::Lista && $exportacion->ruta !== null, 404);

        $organizacion = Organizacion::query()->findOrFail($exportacion->organizacion_afectada_id);
        $traza->registrar(AccionPlataforma::ExportacionDescargada, $organizacion, ['exportacion' => $exportacion->id]);

        $nombre = 'statera-'.str($organizacion->nombre)->slug().'-'.$exportacion->generada_en?->format('Ymd-His').'.zip';

        return Storage::disk(ExportarOrganizacion::DISCO)->download($exportacion->ruta, $nombre);
    }
}
