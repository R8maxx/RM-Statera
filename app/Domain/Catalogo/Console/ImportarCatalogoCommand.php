<?php

declare(strict_types=1);

namespace App\Domain\Catalogo\Console;

use App\Domain\Catalogo\Excepciones\CatalogoInvalido;
use App\Domain\Catalogo\Importador\ImportadorCatalogo;
use App\Domain\Catalogo\Importador\ResultadoImportacion;
use Illuminate\Console\Command;

/**
 * Carga el catálogo normativo desde los YAML del repositorio.
 *
 * El comando solo orquesta y presenta; la lógica vive en ImportadorCatalogo.
 */
final class ImportarCatalogoCommand extends Command
{
    protected $signature = 'catalogo:importar
        {ficheros?* : Ficheros YAML a importar. Por defecto, todo el directorio catalogo/}
        {--dry-run : Muestra el diff sin escribir nada}
        {--diff : Detalla requisito a requisito, no solo el recuento}';

    protected $description = 'Importa el catálogo normativo (ISO 27001, ENS y mapeos) desde catalogo/*.yaml';

    public function handle(ImportadorCatalogo $importador): int
    {
        $simulacion = (bool) $this->option('dry-run');

        /** @var list<string> $ficheros */
        $ficheros = $this->argument('ficheros');

        if ($ficheros === []) {
            $ficheros = $importador->ficherosDe(base_path('catalogo'));
        }

        if ($ficheros === []) {
            $this->components->error('No hay ficheros de catálogo que importar en catalogo/.');

            return self::FAILURE;
        }

        if ($simulacion) {
            $this->components->info('Simulación: no se escribirá nada.');
        }

        $huboCambios = false;

        foreach ($ficheros as $fichero) {
            try {
                $resultado = $importador->importar($fichero, $simulacion);
            } catch (CatalogoInvalido $e) {
                $this->components->error("No se importó [{$this->relativo($e->fichero)}].");

                foreach ($e->errores as $error) {
                    $this->components->bulletList([$error]);
                }

                return self::FAILURE;
            }

            $this->presentar($resultado);
            $huboCambios = $huboCambios || $resultado->hayCambios();
        }

        if (! $huboCambios) {
            $this->newLine();
            $this->components->info('El catálogo ya estaba al día: ningún cambio.');
        }

        return self::SUCCESS;
    }

    private function presentar(ResultadoImportacion $resultado): void
    {
        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=cyan>'.$this->relativo($resultado->fichero).'</>',
            $resultado->marco ?? 'mapeos',
        );

        if ($resultado->tipo === 'mapeos') {
            $this->components->twoColumnDetail('Mapeos nuevos', (string) $resultado->mapeosNuevos);
            $this->components->twoColumnDetail('Mapeos actualizados', (string) $resultado->mapeosActualizados);
            $this->components->twoColumnDetail('Sin cambios', (string) $resultado->sinCambios);

            return;
        }

        $resumen = $resultado->resumen();

        $this->components->twoColumnDetail('Requisitos nuevos', (string) $resumen['nuevos']);
        $this->components->twoColumnDetail('Requisitos modificados', (string) $resumen['modificados']);
        $this->components->twoColumnDetail('Requisitos retirados', (string) $resumen['retirados']);

        if ($resumen['reactivados'] > 0) {
            $this->components->twoColumnDetail('Requisitos reactivados', (string) $resumen['reactivados']);
        }

        $this->components->twoColumnDetail('Sin cambios', (string) $resumen['sin_cambios']);
        $this->components->twoColumnDetail('Refuerzos', (string) $resultado->refuerzos);
        $this->components->twoColumnDetail('Celdas de aplicabilidad', (string) $resultado->celdasAplicabilidad);

        if ($resultado->perfiles > 0) {
            $this->components->twoColumnDetail('Perfiles de cumplimiento', (string) $resultado->perfiles);
        }

        if ($this->option('diff')) {
            $this->detallar($resultado);
        }

        // Al importar una revisión de un marco, el usuario tiene que saber a qué
        // afecta antes de darla por buena. Nada se modifica en silencio.
        if ($resultado->retirados !== []) {
            $this->newLine();
            $this->components->warn(sprintf(
                '%d requisito(s) ya no aparecen en el fichero. No se han borrado: quedan marcados como no vigentes.',
                count($resultado->retirados),
            ));

            $this->components->twoColumnDetail(
                'Implantaciones afectadas',
                (string) $resultado->implantacionesAfectadas,
            );
        }
    }

    private function detallar(ResultadoImportacion $resultado): void
    {
        if ($resultado->nuevos !== []) {
            $this->newLine();
            $this->components->info('Nuevos');
            $this->components->bulletList($resultado->nuevos);
        }

        if ($resultado->modificados !== []) {
            $this->newLine();
            $this->components->info('Modificados');
            $this->components->bulletList(array_map(
                static fn (array $cambio): string => $cambio['codigo'].' → '.implode(', ', $cambio['cambios']),
                $resultado->modificados,
            ));
        }

        if ($resultado->reactivados !== []) {
            $this->newLine();
            $this->components->info('Reactivados');
            $this->components->bulletList($resultado->reactivados);
        }

        if ($resultado->retirados !== []) {
            $this->newLine();
            $this->components->info('Retirados');
            $this->components->bulletList($resultado->retirados);
        }
    }

    private function relativo(string $ruta): string
    {
        return str_replace(base_path().'/', '', $ruta);
    }
}
