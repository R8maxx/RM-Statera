<?php

declare(strict_types=1);

namespace App\Domain\Aviso\Console;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Aviso\Notifications\EvidenciasQueVencen;
use App\Domain\Aviso\ResumenVencimientos;
use App\Domain\Aviso\Vencimientos;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * El resumen diario de vencimientos, una organización cada vez.
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

    public function handle(ResumenVencimientos $resumen, ContextoOrganizacion $contexto): int
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

            $destinatarios = $this->destinatarios($contexto, $organizacion);

            $this->components->twoColumnDetail(
                $organizacion->nombre,
                sprintf(
                    '%d caducada(s), %d por caducar → %d destinatario(s)',
                    count($vencimientos->caducadas),
                    count($vencimientos->porCaducar),
                    $destinatarios->count(),
                ),
            );

            if ($simulacion || $destinatarios->isEmpty()) {
                continue;
            }

            Notification::send(
                $destinatarios,
                new EvidenciasQueVencen($organizacion->nombre, $vencimientos),
            );

            $enviados++;
        }

        if ($enviados === 0 && ! $simulacion) {
            $this->components->info('Nada que avisar.');
        }

        return self::SUCCESS;
    }

    /**
     * Quien puede hacer algo con el aviso.
     *
     * El responsable de seguridad, que es quien responde de que la prueba exista.
     * El técnico ve sus evidencias en la herramienta; recibir además el resumen
     * de toda la organización es ruido para él y nadie lee un correo que casi
     * nunca le toca.
     *
     * @return Collection<int, User>
     */
    private function destinatarios(ContextoOrganizacion $contexto, Organizacion $organizacion): Collection
    {
        // Dentro del contexto porque los roles de spatie van por «team»: fuera
        // de él, `role()` miraría los de otra organización o los de ninguna.
        return $contexto->paraOrganizacion($organizacion, fn () => User::query()
            ->where('organizacion_id', $organizacion->id)
            ->role(Rol::ResponsableSeguridad->value)
            ->get());
    }
}
