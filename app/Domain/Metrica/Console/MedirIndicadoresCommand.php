<?php

declare(strict_types=1);

namespace App\Domain\Metrica\Console;

use App\Domain\Metrica\Models\Indicador;
use App\Domain\Metrica\RegistrarMedicion;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Cierra el periodo de los indicadores calculados, una organización cada vez.
 *
 * **Esto es lo que convierte una cifra en una serie.** El panel lleva desde el
 * principio contando lo mismo; la diferencia es que aquí la cifra queda sellada
 * con su periodo, su denominador y el objetivo que estaba puesto ese día, y ya
 * no cambia. Sin este paso, «¿ha mejorado desde la última revisión?» —la
 * pregunta de la 9.3— no tiene contra qué compararse.
 *
 * **Mismo cuidado que `avisos:enviar`, y por el mismo motivo**: un comando
 * programado no tiene petición ni usuario, así que sin contexto el scope de
 * Eloquent no devuelve nada y RLS deniega por defecto. No falla: **no ve nada**,
 * y una serie que se queda sin puntos es indistinguible de una organización que
 * no mide. De ahí `ContextoOrganizacion::paraOrganizacion()`, que pone las tres
 * capas y las devuelve a su sitio aunque algo lance.
 *
 * Nada de `withoutGlobalScopes()` ni de `comoMantenimiento()`: esto no cruza
 * organizaciones, las visita de una en una.
 *
 * **Sólo mide el periodo ya cerrado**, y sólo si no estaba medido. Medir el que
 * está en curso daría una cifra a medias que habría que corregir al día
 * siguiente; volver a medir uno ya sellado reescribiría el pasado, que es
 * justamente lo que este módulo existe para impedir. La corrección tiene su
 * propio camino, con autor y traza.
 *
 * Los indicadores **manuales no se tocan**: su cifra no está en esta base de
 * datos, y sellar un cero en su nombre sería inventarse una medición.
 */
final class MedirIndicadoresCommand extends Command
{
    protected $signature = 'indicadores:medir
        {--fecha= : Qué día se toma como hoy, para cerrar el periodo anterior}
        {--forzar : Vuelve a medir el periodo aunque ya tenga cifra}
        {--dry-run : Enseña lo que se sellaría, sin escribir nada}';

    protected $description = 'Sella la medición del último periodo cerrado de cada indicador calculado';

    public function handle(ContextoOrganizacion $contexto, RegistrarMedicion $registrar): int
    {
        $hoy = $this->option('fecha') !== null
            ? Carbon::parse((string) $this->option('fecha'))
            : Carbon::today();

        $simulacion = (bool) $this->option('dry-run');
        $forzar = (bool) $this->option('forzar');
        $selladas = 0;

        foreach (Organizacion::query()->orderBy('id')->cursor() as $organizacion) {
            $selladas += $contexto->paraOrganizacion(
                $organizacion,
                fn (): int => $this->deLaOrganizacion($organizacion->nombre, $hoy, $simulacion, $forzar, $registrar),
            );
        }

        if ($selladas === 0) {
            $this->components->info('Ningún periodo pendiente de medir.');
        }

        return self::SUCCESS;
    }

    private function deLaOrganizacion(string $nombre, Carbon $hoy, bool $simulacion, bool $forzar, RegistrarMedicion $registrar): int
    {
        $selladas = 0;

        $indicadores = Indicador::query()
            ->activos()
            ->whereNotNull('calculo')
            ->orderBy('codigo')
            ->get();

        foreach ($indicadores as $indicador) {
            [$inicio, $fin] = $indicador->periodoACerrar($hoy);

            if (! $forzar && ! $indicador->tienePeriodoSinMedir($hoy)) {
                continue;
            }

            $etiqueta = $indicador->periodicidad->etiquetaDe($inicio);

            if ($simulacion) {
                // En simulación se calcula igual: lo que interesa ver antes de
                // sellar es la cifra, no que el indicador estaba pendiente.
                $medida = $indicador->calculo?->medir($indicador->marco_id);

                $this->components->twoColumnDetail(
                    sprintf('%s · %s · %s', $nombre, $indicador->codigo, $etiqueta),
                    $medida === null ? '—' : $indicador->unidad->escribir($medida->valor),
                );

                $selladas++;

                continue;
            }

            $medicion = $registrar->calculada($indicador, $inicio, $fin);

            $this->components->twoColumnDetail(
                sprintf('%s · %s · %s', $nombre, $indicador->codigo, $etiqueta),
                $indicador->unidad->escribir((float) $medicion->valor),
            );

            $selladas++;
        }

        return $selladas;
    }
}
