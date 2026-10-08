<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Enums;

use App\Domain\Organizacion\Models\Organizacion;
use Illuminate\Support\Carbon;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Los momentos en que se avisa por correo de que la suscripción vence (punto 46).
 *
 * Cinco y no uno: con un mes hay tiempo de renovar sin prisas, con una semana
 * hay que moverse, y el día antes es el último recordatorio. Después, el día que
 * entra en gracia y el día que pasa a sólo lectura, que son los dos cambios que
 * el cliente nota.
 *
 * **Sólo se manda el hito en que está hoy**, no los anteriores que se hubieran
 * perdido: si el planificador estuvo parado una semana, nadie necesita recibir
 * a la vez «faltan 30 días» y «faltan 7».
 */
#[TypeScript]
enum HitoSuscripcion: string
{
    case Faltan30 = 'faltan_30';
    case Faltan7 = 'faltan_7';
    case Falta1 = 'falta_1';
    case EnGracia = 'en_gracia';
    case SoloLectura = 'solo_lectura';

    /** El hito en que está hoy la suscripción, o nulo si no toca avisar. */
    public static function de(Organizacion $organizacion, ?Carbon $ahora = null): ?self
    {
        $ahora ??= Carbon::now();
        $vence = $organizacion->suscripcion_vence_en;

        if ($vence === null || $organizacion->plan === null) {
            return null;
        }

        return match (EstadoSuscripcion::de($organizacion, $ahora)) {
            EstadoSuscripcion::SoloLectura => self::SoloLectura,
            EstadoSuscripcion::EnGracia => self::EnGracia,
            // Por días de calendario y no por horas: el aviso de las 07:15 del día
            // anterior tiene que decir «mañana» aunque falten 40 horas.
            EstadoSuscripcion::Vigente => self::antesDeVencer(
                (int) $ahora->copy()->startOfDay()->diffInDays($vence->copy()->startOfDay(), false),
            ),
        };
    }

    private static function antesDeVencer(int $dias): ?self
    {
        return match (true) {
            $dias <= 1 => self::Falta1,
            $dias <= 7 => self::Faltan7,
            $dias <= 30 => self::Faltan30,
            default => null,
        };
    }

    public function asunto(): string
    {
        return match ($this) {
            self::Faltan30 => 'Tu suscripción a Statera vence en un mes',
            self::Faltan7 => 'Tu suscripción a Statera vence en una semana',
            self::Falta1 => 'Tu suscripción a Statera vence mañana',
            self::EnGracia => 'Tu suscripción a Statera ha vencido',
            self::SoloLectura => 'Statera ha pasado a sólo lectura',
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Faltan30 => 'Vence en un mes',
            self::Faltan7 => 'Vence en una semana',
            self::Falta1 => 'Vence mañana',
            self::EnGracia => 'En periodo de gracia',
            self::SoloLectura => 'En sólo lectura',
        };
    }
}
