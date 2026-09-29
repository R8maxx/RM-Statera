<?php

declare(strict_types=1);

namespace App\Domain\Persona\Console;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Persona\Excepciones\SeudonimizacionNoPermitida;
use App\Domain\Persona\Models\Persona;
use App\Domain\Persona\SeudonimizarPersona;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Aplica la retención de cada organización (§ 6, punto 36): suprime los datos
 * de quien se fue hace más de lo que la organización decidió conservar.
 *
 * **Sólo en las organizaciones que han declarado un plazo.** Sin plazo no se
 * toca a nadie, porque el número lo pone la organización y no la herramienta.
 *
 * Visita las organizaciones de una en una con `paraOrganizacion()`, como el
 * resumen de avisos. No cruza organizaciones, así que no usa
 * `comoMantenimiento()`.
 *
 * Quien tiene un nombramiento vigente **no se suprime y se cuenta**: la
 * acción lo impide, y aquí se enseña para que alguien cierre el nombramiento.
 */
final class SeudonimizarPersonasCommand extends Command
{
    protected $signature = 'personas:seudonimizar
        {--dry-run : Cuenta a quién le toca, sin suprimir nada}';

    protected $description = 'Suprime los datos personales de quien pasó el plazo de retención de su organización';

    public function handle(ContextoOrganizacion $contexto, SeudonimizarPersona $seudonimizar): int
    {
        $simulacion = (bool) $this->option('dry-run');

        $organizaciones = Organizacion::query()
            ->whereNotNull('retencion_personas_meses')
            ->orderBy('id')
            ->get();

        foreach ($organizaciones as $organizacion) {
            [$suprimidas, $bloqueadas] = $contexto->paraOrganizacion(
                $organizacion,
                fn (): array => $this->aplicar($organizacion, $seudonimizar, $simulacion),
            );

            $this->components->twoColumnDetail(
                $organizacion->nombre,
                sprintf(
                    '%d %s, %d con nombramientos vigentes',
                    $suprimidas,
                    $simulacion ? 'por suprimir' : 'suprimidas',
                    count($bloqueadas),
                ),
            );

            foreach ($bloqueadas as $mensaje) {
                $this->components->warn($mensaje);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: list<string>}
     */
    private function aplicar(Organizacion $organizacion, SeudonimizarPersona $seudonimizar, bool $simulacion): array
    {
        $limite = Carbon::today()->subMonths((int) $organizacion->retencion_personas_meses);

        $vencidas = Persona::query()
            ->whereNull('seudonimizada_en')
            ->whereNotNull('fecha_baja')
            ->whereDate('fecha_baja', '<=', $limite)
            ->orderBy('id')
            ->get();

        $suprimidas = 0;
        $bloqueadas = [];

        foreach ($vencidas as $persona) {
            if ($simulacion) {
                $suprimidas++;

                continue;
            }

            try {
                $seudonimizar($persona);
                $suprimidas++;
            } catch (SeudonimizacionNoPermitida $motivo) {
                $bloqueadas[] = $motivo->getMessage();
            }
        }

        return [$suprimidas, $bloqueadas];
    }
}
