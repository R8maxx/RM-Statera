<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Exportacion;

use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\EstadoExportacion;
use App\Domain\Plataforma\Jobs\ExportarOrganizacionJob;
use App\Domain\Plataforma\Models\ExportacionOrganizacion;
use App\Domain\Plataforma\TrazaPlataforma;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Pide la exportación de un cliente (punto 56): deja la fila y encola el job.
 * Si ya hay una en curso, no se encola otra.
 */
final class SolicitarExportacion
{
    public function __construct(private readonly TrazaPlataforma $traza) {}

    public function __invoke(User $administrador, Organizacion $organizacion): ExportacionOrganizacion
    {
        $enCurso = ExportacionOrganizacion::query()
            ->where('organizacion_afectada_id', $organizacion->id)
            ->where('estado', EstadoExportacion::EnCurso->value)
            ->first();

        if ($enCurso !== null) {
            return $enCurso;
        }

        $exportacion = ExportacionOrganizacion::query()->create([
            'organizacion_afectada_id' => $organizacion->id,
            'solicitada_por' => $administrador->id,
            'estado' => EstadoExportacion::EnCurso->value,
            'solicitada_en' => Carbon::now(),
        ]);

        $this->traza->registrar(AccionPlataforma::ExportacionSolicitada, $organizacion, ['exportacion' => $exportacion->id]);

        ExportarOrganizacionJob::dispatch($exportacion->id, $organizacion->id);

        return $exportacion->refresh();
    }
}
