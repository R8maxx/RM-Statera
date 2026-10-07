<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Enums;

use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Support\Carbon;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * En qué punto está la suscripción de una organización (punto 43).
 *
 * **No se guarda: se deriva** de la fecha de vencimiento y de los días de gracia
 * del plan, igual que `EstadoCuenta`. Vencer es que la fecha ya pasó, y eso no
 * necesita que nadie cambie una columna a medianoche.
 *
 * Sin plan o sin fecha de vencimiento la suscripción no vence: es el estado de
 * toda organización anterior al punto 43, y el del uso interno.
 *
 * **Sólo lectura no es perder nada.** La organización sigue entrando, viendo y
 * descargando todo lo que tiene —la SoA que ya entregó, las evidencias que ya
 * vio el auditor—; lo que no puede es escribir.
 */
#[TypeScript]
enum EstadoSuscripcion: string
{
    case Vigente = 'vigente';
    case EnGracia = 'en_gracia';
    case SoloLectura = 'solo_lectura';

    public static function de(Organizacion $organizacion, ?Carbon $ahora = null): self
    {
        $ahora ??= Carbon::now();
        $vence = $organizacion->suscripcion_vence_en;

        if ($vence === null || $organizacion->plan === null || $ahora->lte($vence)) {
            return self::Vigente;
        }

        return $ahora->lte(self::finDeGracia($organizacion) ?? $vence)
            ? self::EnGracia
            : self::SoloLectura;
    }

    /** El último instante en que todavía se puede escribir, o nulo si no vence. */
    public static function finDeGracia(Organizacion $organizacion): ?Carbon
    {
        if ($organizacion->suscripcion_vence_en === null || $organizacion->plan === null) {
            return null;
        }

        return $organizacion->suscripcion_vence_en->copy()->addDays($organizacion->plan->dias_gracia);
    }

    /** Si la organización puede escribir. En gracia, sí: se avisa, no se corta. */
    public function permiteEscribir(): bool
    {
        return $this !== self::SoloLectura;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Vigente => 'Vigente',
            self::EnGracia => 'En periodo de gracia',
            self::SoloLectura => 'Sólo lectura',
        };
    }

    /*
     * El rojo sólo para sólo lectura, que es lo único que va mal de verdad: el
     * cliente ya no puede trabajar. En gracia todavía puede, y se le avisa.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Vigente => 'implantado',
            self::EnGracia => 'en_revision',
            self::SoloLectura => 'caducada',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Vigente => 'CircleCheck',
            self::EnGracia => 'CalendarClock',
            self::SoloLectura => 'Lock',
        };
    }
}
