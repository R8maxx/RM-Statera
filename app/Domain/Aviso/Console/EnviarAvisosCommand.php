<?php

declare(strict_types=1);

namespace App\Domain\Aviso\Console;

use App\Domain\Aviso\DestinatariosDelResumen;
use App\Domain\Aviso\Notifications\VencimientosDelDia;
use App\Domain\Aviso\ResumenVencimientos;
use App\Domain\Aviso\Vencimientos;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * El resumen diario de vencimientos —evidencias que caducan y tareas que vencen—,
 * una organización cada vez.
 *
 * **Aquí es donde esto se rompe si se escribe deprisa.** Un comando programado
 * no tiene petición ni usuario, así que no hay contexto de organización: el
 * scope de Eloquent no devuelve nada y RLS deniega por defecto. El comando **no
 * falla, sencillamente no ve nada**, y un aviso que no salta es indistinguible
 * de no tener nada que avisar. De ahí que se recorra con
 * `ContextoOrganizacion::paraOrganizacion()`, que pone las tres capas y las
 * devuelve a su sitio incluso si algo lanza.
 *
 * Nada de `withoutGlobalScopes()` ni de `comoMantenimiento()`: esto no cruza
 * organizaciones, las visita de una en una.
 */
final class EnviarAvisosCommand extends Command
{
    protected $signature = 'avisos:enviar
        {--dias= : Cuántos días mirar hacia delante}
        {--dry-run : Cuenta lo que saldría, sin enviar nada}';

    protected $description = 'Envía a cada organización el resumen de lo que vence';

    public function handle(ResumenVencimientos $resumen, ContextoOrganizacion $contexto, DestinatariosDelResumen $destinatarios): int
    {
        $dias = (int) ($this->option('dias') ?: ResumenVencimientos::DIAS);
        $simulacion = (bool) $this->option('dry-run');
        $enviados = 0;

        foreach (Organizacion::query()->orderBy('id')->cursor() as $organizacion) {
            $vencimientos = $contexto->paraOrganizacion(
                $organizacion,
                fn (): Vencimientos => $resumen($dias),
            );

            if (! $vencimientos->hayAlgo()) {
                continue;
            }

            $aQuien = $destinatarios->de($organizacion);

            $this->components->twoColumnDetail(
                $organizacion->nombre,
                sprintf(
                    '%d pasada(s) de fecha, %d por vencer → %d destinatario(s)',
                    $vencimientos->pasados(),
                    $vencimientos->total() - $vencimientos->pasados(),
                    $aQuien->count(),
                ),
            );

            if ($simulacion || $aQuien->isEmpty()) {
                continue;
            }

            Notification::send(
                $aQuien,
                new VencimientosDelDia($organizacion->nombre, $vencimientos),
            );

            $enviados++;
        }

        if ($enviados === 0 && ! $simulacion) {
            $this->components->info('Nada que avisar.');
        }

        return self::SUCCESS;
    }
}
