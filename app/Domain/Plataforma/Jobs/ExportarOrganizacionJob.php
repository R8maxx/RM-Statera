<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Jobs;

use App\Domain\Organizacion\Jobs\ConContextoDeOrganizacion;
use App\Domain\Plataforma\Enums\EstadoExportacion;
use App\Domain\Plataforma\Exportacion\ExportarOrganizacion;
use App\Domain\Plataforma\Models\ExportacionOrganizacion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * La exportación de un cliente, en cola (punto 56).
 *
 * **Escalares y no `SerializesModels`**, como todo job con contexto: el modelo
 * se reconsultaría al deserializar, antes de que haya contexto. Y con su propio
 * `failed()`, porque ése no pasa por el middleware y sin él la exportación se
 * quedaría «preparándose» para siempre.
 */
final class ExportarOrganizacionJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public readonly int $exportacionId,
        public readonly int $organizacionId,
    ) {
        $this->onQueue('documentos');
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new ConContextoDeOrganizacion($this->organizacionId)];
    }

    public function handle(ExportarOrganizacion $exportar): void
    {
        $exportacion = ExportacionOrganizacion::query()->find($this->exportacionId);

        if ($exportacion === null || $exportacion->estado !== EstadoExportacion::EnCurso) {
            return;
        }

        $exportar($exportacion);
    }

    public function failed(?Throwable $error): void
    {
        ExportacionOrganizacion::query()->whereKey($this->exportacionId)->update([
            'estado' => EstadoExportacion::Fallida->value,
            'error' => $error === null ? null : class_basename($error),
        ]);
    }
}
