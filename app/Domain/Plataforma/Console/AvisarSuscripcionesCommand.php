<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Console;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\EstadoSuscripcion;
use App\Domain\Plataforma\Enums\HitoSuscripcion;
use App\Domain\Plataforma\Models\AvisoSuscripcion;
use App\Domain\Plataforma\Notifications\ResumenDeVencimientos;
use App\Domain\Plataforma\Notifications\VencimientoDeSuscripcion;
use App\Domain\Plataforma\TrazaPlataforma;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Avisa por correo de que una suscripción vence o ha vencido (punto 46).
 *
 * Al responsable de seguridad de cada cliente, en cinco momentos: 30, 7 y 1
 * días antes, al entrar en gracia y al pasar a sólo lectura. Y a quien
 * administra la plataforma, un resumen de a quién se ha avisado.
 *
 * **Cada aviso sale una vez**: `avisos_suscripcion` guarda qué hito se mandó
 * para qué vencimiento. Al renovar, el vencimiento cambia y los avisos vuelven
 * a empezar.
 *
 * **Y cada aviso queda en la traza de la plataforma**, con el hito y a quién
 * fue, para que la ficha del cliente conteste «¿le avisamos?» sin mirar la
 * base. También cuando no había ningún responsable que pudiera recibirlo: ese
 * es justo el aviso que conviene ver.
 *
 * **No necesita contexto de organización**: lee `organizaciones`, `planes`,
 * `users` y `avisos_suscripcion`, que no están bajo RLS. Las organizaciones de
 * baja se saltan, porque nadie suyo puede entrar a renovar.
 */
final class AvisarSuscripcionesCommand extends Command
{
    protected $signature = 'suscripciones:avisar
        {--dry-run : Enseña a quién se avisaría, sin enviar ni anotar nada}';

    protected $description = 'Avisa a cada cliente de que su suscripción vence o ha vencido';

    public function handle(TrazaPlataforma $traza): int
    {
        $simulacion = (bool) $this->option('dry-run');
        $resumen = [];

        $organizaciones = Organizacion::query()
            ->with('plan')
            ->whereNull('baja_en')
            ->whereNotNull('plan_id')
            ->whereNotNull('suscripcion_vence_en')
            ->orderBy('id')
            ->get();

        foreach ($organizaciones as $organizacion) {
            $hito = HitoSuscripcion::de($organizacion);
            $vence = $organizacion->suscripcion_vence_en;

            if ($hito === null || $vence === null || $this->yaAvisado($organizacion, $hito)) {
                continue;
            }

            $responsables = $this->responsables($organizacion);

            $this->components->twoColumnDetail(
                $organizacion->nombre,
                sprintf('%s → %d responsable(s)', $hito->etiqueta(), count($responsables)),
            );

            $resumen[] = "{$organizacion->nombre}: ".mb_strtolower($hito->etiqueta());

            if ($simulacion) {
                continue;
            }

            $zona = (string) config('app.timezone');
            $graciaHasta = EstadoSuscripcion::finDeGracia($organizacion);

            Notification::send($responsables, new VencimientoDeSuscripcion(
                $hito,
                $organizacion->nombre,
                (string) $organizacion->plan?->nombre,
                $vence->copy()->timezone($zona)->format('d/m/Y'),
                $graciaHasta?->copy()->timezone($zona)->format('d/m/Y'),
            ));

            AvisoSuscripcion::query()->create([
                'organizacion_afectada_id' => $organizacion->id,
                'hito' => $hito->value,
                'vence_en' => $vence,
                'enviado_en' => now(),
            ]);

            $traza->registrar(AccionPlataforma::AvisoVencimientoEnviado, $organizacion, [
                'hito' => $hito->value,
                'vence_en' => $vence->toIso8601String(),
                'destinatarios' => array_map(static fn (User $cuenta): string => $cuenta->email, $responsables),
            ]);
        }

        if ($resumen === []) {
            $this->components->info('Nada que avisar.');

            return self::SUCCESS;
        }

        if (! $simulacion) {
            Notification::send($this->administradores(), new ResumenDeVencimientos($resumen));
        }

        return self::SUCCESS;
    }

    private function yaAvisado(Organizacion $organizacion, HitoSuscripcion $hito): bool
    {
        return AvisoSuscripcion::query()
            ->where('organizacion_afectada_id', $organizacion->id)
            ->where('hito', $hito->value)
            ->where('vence_en', $organizacion->suscripcion_vence_en)
            ->exists();
    }

    /**
     * Los responsables de seguridad que pueden entrar: los que pueden renovar
     * o hablar con quien renueva.
     *
     * @return list<User>
     */
    private function responsables(Organizacion $organizacion): array
    {
        return User::query()
            ->where('organizacion_id', $organizacion->id)
            ->whereNull('desactivada_en')
            ->whereNotNull('activada_en')
            ->get()
            ->filter(static fn (User $cuenta): bool => $cuenta->rol() === Rol::ResponsableSeguridad)
            ->values()
            ->all();
    }

    /**
     * Quien administra la plataforma y puede entrar.
     *
     * @return list<User>
     */
    private function administradores(): array
    {
        return User::query()
            // Sin acotar a una organización, a propósito: los administradores
            // son de la plataforma, sea cual sea la suya (organizacion_id).
            ->where('es_plataforma', true)
            ->whereNull('desactivada_en')
            ->whereNotNull('activada_en')
            ->get()
            ->all();
    }
}
