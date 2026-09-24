<?php

declare(strict_types=1);

namespace App\Domain\Usuario\Enums;

use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * En qué punto de su vida está una cuenta (§ 4.19).
 *
 * **No se guarda: se deriva** de cuatro fechas de `users`. Caducar es que
 * `acceso_hasta` ya pasó, y eso no necesita que nadie cambie una columna a las
 * cero horas — la misma decisión que la conformidad del § 4.17.
 *
 * El orden de `de()` es el de precedencia: una cuenta desactivada lo está
 * aunque además haya caducado, y una invitación que nadie aceptó y que se
 * desactivó no es una invitación pendiente.
 */
#[TypeScript]
enum EstadoCuenta: string
{
    case Invitada = 'invitada';
    case Activa = 'activa';
    case Caducada = 'caducada';
    case Desactivada = 'desactivada';

    public static function de(User $usuario, ?Carbon $hoy = null): self
    {
        $hoy ??= Carbon::today();

        return match (true) {
            $usuario->desactivada_en !== null => self::Desactivada,
            $usuario->acceso_hasta !== null && $usuario->acceso_hasta->lt($hoy) => self::Caducada,
            $usuario->activada_en === null => self::Invitada,
            default => self::Activa,
        };
    }

    /** Sólo una cuenta activa entra. Una invitada entra después de aceptar. */
    public function puedeEntrar(): bool
    {
        return $this === self::Activa;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Invitada => 'Invitada',
            self::Activa => 'Activa',
            self::Caducada => 'Caducada',
            self::Desactivada => 'Desactivada',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Invitada => 'Tiene la invitación en el correo y todavía no ha fijado su contraseña.',
            self::Activa => 'Entra y trabaja con los permisos de su rol.',
            self::Caducada => 'Su fecha de acceso ya pasó. No entra hasta que alguien la amplíe.',
            self::Desactivada => 'No entra. Sigue apareciendo como autora de lo que hizo.',
        };
    }

    /*
     * Ninguna de las cuatro gasta el rojo: una cuenta caducada es lo que se
     * esperaba el día que se puso la fecha, no algo que vaya mal. Desactivada y
     * caducada comparten gris y se distinguen por el icono, que es lo que pide
     * DESIGN.md § 11.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Invitada => 'planificado',
            self::Activa => 'implantado',
            self::Caducada, self::Desactivada => 'no_aplica',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Invitada => 'MailClock',
            self::Activa => 'CircleCheck',
            self::Caducada => 'CalendarX',
            self::Desactivada => 'UserX',
        };
    }
}
