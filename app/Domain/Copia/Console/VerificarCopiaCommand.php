<?php

declare(strict_types=1);

namespace App\Domain\Copia\Console;

use App\Domain\Copia\VerificarCopia;
use Illuminate\Console\Command;

/**
 * La restauración probada del § 6: restaura la última copia —o la que se
 * nombre— en una base aparte y la compara con lo que se copió.
 *
 * **Sale con error si algo no cuadra**, para que el planificador lo cuente como
 * fallo y no como una línea más del registro. Una verificación que falla en
 * silencio es peor que ninguna, porque deja creer que hay copia.
 */
final class VerificarCopiaCommand extends Command
{
    protected $signature = 'copias:verificar
        {copia? : Nombre de la copia; por defecto, la última}';

    protected $description = 'Restaura una copia en una base aparte y comprueba tablas y ficheros';

    public function handle(VerificarCopia $verificar): int
    {
        $nombre = $this->argument('copia');
        $resultado = $verificar(is_string($nombre) ? $nombre : null);

        $this->components->twoColumnDetail('Copia', $resultado->manifiesto->nombre);
        $this->components->twoColumnDetail(
            'Tablas',
            sprintf('%d, %d con otro número de filas', $resultado->tablas, count($resultado->tablasDistintas)),
        );
        $this->components->twoColumnDetail(
            'Ficheros',
            sprintf(
                '%d comprobados, %d ausentes, %d con otra huella',
                $resultado->ficherosComprobados,
                count($resultado->ficherosAusentes),
                count($resultado->ficherosDistintos),
            ),
        );

        foreach ($resultado->tablasDistintas as $tabla => ['esperadas' => $esperadas, 'restauradas' => $restauradas]) {
            $this->components->error(sprintf('%s: %s filas al copiar, %s al restaurar', $tabla, $esperadas ?? 'ninguna', $restauradas ?? 'ninguna'));
        }

        foreach ([...$resultado->ficherosAusentes, ...$resultado->ficherosDistintos] as $fichero) {
            $this->components->error($fichero);
        }

        if (! $resultado->correcta()) {
            $this->components->error('La copia no se puede dar por buena.');

            return self::FAILURE;
        }

        $this->components->info('La copia se restaura entera y sus ficheros son los que se copiaron.');

        return self::SUCCESS;
    }
}
