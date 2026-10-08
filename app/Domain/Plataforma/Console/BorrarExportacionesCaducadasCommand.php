<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Console;

use App\Domain\Plataforma\Enums\EstadoExportacion;
use App\Domain\Plataforma\Exportacion\ExportarOrganizacion;
use App\Domain\Plataforma\Models\ExportacionOrganizacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Borra los ZIP de exportación que han caducado (punto 56). Una exportación son
 * todos los datos de un cliente: no se quedan en el disco más de lo necesario.
 * La fila se conserva, en estado caducada, como constancia.
 */
final class BorrarExportacionesCaducadasCommand extends Command
{
    protected $signature = 'exportaciones:borrar-caducadas';

    protected $description = 'Borra los ficheros de exportación de clientes que han caducado';

    public function handle(): int
    {
        $caducadas = ExportacionOrganizacion::query()
            ->where('estado', EstadoExportacion::Lista->value)
            ->where('caduca_en', '<', now())
            ->get();

        foreach ($caducadas as $exportacion) {
            if ($exportacion->ruta !== null) {
                Storage::disk(ExportarOrganizacion::DISCO)->delete($exportacion->ruta);
            }

            $exportacion->forceFill(['estado' => EstadoExportacion::Caducada->value, 'ruta' => null])->save();
        }

        $this->components->info(count($caducadas).' exportación(es) caducada(s) borrada(s).');

        return self::SUCCESS;
    }
}
