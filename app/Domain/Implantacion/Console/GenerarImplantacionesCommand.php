<?php

declare(strict_types=1);

namespace App\Domain\Implantacion\Console;

use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Implantacion\ResultadoGeneracion;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Sistema\Models\Sistema;
use Illuminate\Console\Command;

/**
 * Genera o recalcula el conjunto de implantaciones de un sistema.
 *
 * Mismo reparto de papeles que `catalogo:importar`: el comando orquesta y
 * presenta, la lógica vive en el dominio, y `--dry-run` enseña el diff sin
 * escribir nada.
 */
final class GenerarImplantacionesCommand extends Command
{
    protected $signature = 'implantaciones:generar
        {sistema : Id del sistema}
        {--dry-run : Muestra el diff sin escribir nada}
        {--diff : Detalla medida a medida, no sólo el recuento}';

    protected $description = 'Genera las implantaciones de un sistema a partir de su valoración y del catálogo';

    public function handle(GeneradorImplantaciones $generador, ContextoOrganizacion $contexto): int
    {
        $simulacion = (bool) $this->option('dry-run');

        // El comando corre sin sesión, así que no hay organización activa: se
        // localiza el sistema en modo mantenimiento y a partir de ahí se trabaja
        // dentro de su organización, con las tres capas puestas.
        $sistema = $contexto->comoMantenimiento(
            fn (): ?Sistema => Sistema::query()->find((int) $this->argument('sistema'))
        );

        if ($sistema === null) {
            $this->components->error("No existe el sistema [{$this->argument('sistema')}].");

            return self::FAILURE;
        }

        if ($simulacion) {
            $this->components->info('Simulación: no se escribirá nada.');
        }

        $resultado = $contexto->paraOrganizacion(
            $sistema->organizacion_id,
            fn (): ResultadoGeneracion => $generador->generar($sistema, $simulacion),
        );

        $this->presentar($resultado);

        return self::SUCCESS;
    }

    private function presentar(ResultadoGeneracion $resultado): void
    {
        $this->newLine();
        $this->components->twoColumnDetail("<fg=cyan>{$resultado->sistema}</>", $resultado->marco);
        $this->components->twoColumnDetail('Categoría', $resultado->categoria ?? 'fuera del ámbito del ENS');

        $resumen = $resultado->resumen();

        $this->components->twoColumnDetail('Implantaciones creadas', (string) $resumen['creadas']);
        $this->components->twoColumnDetail('Reactivadas', (string) $resumen['reactivadas']);
        $this->components->twoColumnDetail('Dejan de aplicar', (string) $resumen['dejan_de_aplicar']);
        $this->components->twoColumnDetail('Cambian de exigencia', (string) $resumen['cambian_exigencia']);
        $this->components->twoColumnDetail('Sin cambios', (string) $resumen['sin_cambios']);

        if ($this->option('diff')) {
            $this->detallar($resultado);
        }

        // Nada se modifica en silencio: lo que deja de exigirse se dice, y se
        // dice que no se ha borrado.
        if ($resultado->dejanDeAplicar !== []) {
            $this->newLine();
            $this->components->warn(sprintf(
                '%d medida(s) dejan de exigirse. No se han borrado: quedan marcadas como no aplicables, con su histórico intacto.',
                count($resultado->dejanDeAplicar),
            ));
        }

        if (! $resultado->hayCambios()) {
            $this->newLine();
            $this->components->info('El sistema ya estaba al día: ningún cambio.');
        }
    }

    private function detallar(ResultadoGeneracion $resultado): void
    {
        foreach ([
            'Creadas' => $resultado->creadas,
            'Reactivadas' => $resultado->reactivadas,
            'Dejan de aplicar' => $resultado->dejanDeAplicar,
        ] as $titulo => $codigos) {
            if ($codigos === []) {
                continue;
            }

            $this->newLine();
            $this->components->info($titulo);
            $this->components->bulletList($codigos);
        }

        if ($resultado->cambianExigencia !== []) {
            $this->newLine();
            $this->components->info('Cambian de exigencia');
            $this->components->bulletList(array_map(
                static fn (array $cambio): string => "{$cambio['codigo']}: {$cambio['anterior']} → {$cambio['nueva']}",
                $resultado->cambianExigencia,
            ));
        }
    }
}
