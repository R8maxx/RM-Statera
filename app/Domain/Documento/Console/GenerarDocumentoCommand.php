<?php

declare(strict_types=1);

namespace App\Domain\Documento\Console;

use App\Domain\Documento\Contenido\RegistroGeneradores;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Render\GraficaSvg;
use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;

/**
 * Genera un documento desde la consola.
 *
 * `--html` es la opción que más se usa y la que menos lo parece: vuelca el HTML
 * sin pasar por Gotenberg, y el noventa por ciento del trabajo de plantilla se
 * hace mirándolo en un navegador. `--sync` genera el PDF de verdad, en este
 * proceso, sin depender de que haya un worker levantado.
 */
final class GenerarDocumentoCommand extends Command
{
    protected $signature = 'documentos:generar
        {codigo : Código del documento, por ejemplo SOA-SGSI-01}
        {--sync : Genera el PDF en este proceso en lugar de encolarlo}
        {--html : Vuelca el HTML por la salida estándar y no genera ningún PDF}';

    protected $description = 'Genera un documento (SoA, DdA) o vuelca su HTML para trabajar la plantilla';

    public function handle(
        ContextoOrganizacion $contexto,
        GenerarDocumento $generar,
        RegistroGeneradores $generadores,
        GraficaSvg $graficas,
    ): int {
        /*
         * Un comando tampoco pasa por `EstablecerContextoOrganizacion`: sin
         * contexto, el scope y RLS no devuelven ninguna fila y esto diría que el
         * documento no existe. Se busca en modo mantenimiento y se sigue dentro
         * de la organización que resulte.
         */
        $documento = $contexto->comoMantenimiento(
            fn (): ?Documento => Documento::query()->where('codigo', $this->argument('codigo'))->first(),
        );

        if ($documento === null) {
            $this->components->error("No hay ningún documento con código [{$this->argument('codigo')}].");

            return self::FAILURE;
        }

        return $contexto->paraOrganizacion($documento->organizacion_id, function () use ($documento, $generar, $generadores, $graficas): int {
            $documento->refresh();

            if ($this->option('html')) {
                $this->volcarHtml($documento, $generar, $generadores, $graficas);

                return self::SUCCESS;
            }

            $version = $generar->encolar($documento);

            if (! $this->option('sync')) {
                $this->components->info("Generación de {$documento->codigo} encolada en «documentos».");

                return self::SUCCESS;
            }

            $generar->ejecutar($version);
            $version->refresh();

            $this->components->info("{$documento->codigo} generado: {$version->ruta}");
            $this->components->twoColumnDetail('SHA-256', (string) $version->hash_sha256);
            $this->components->twoColumnDetail('Tamaño', number_format((int) $version->tamano / 1024, 1).' kB');

            return self::SUCCESS;
        });
    }

    private function volcarHtml(
        Documento $documento,
        GenerarDocumento $generar,
        RegistroGeneradores $generadores,
        GraficaSvg $graficas,
    ): void {
        $version = $generar->encolar($documento);

        $contenido = $generadores->para($documento->tipo)->construir($documento, $version, []);

        $this->output->writeln(View::make($documento->tipo->plantilla(), [
            'contenido' => $contenido,
            'barra' => $graficas->barraPorEstado($contenido->resumen['segmentos']),
        ])->render());
    }
}
