<?php

declare(strict_types=1);

namespace App\Domain\Autorizacion\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Los permisos de la aplicación, por módulo y verbo.
 *
 * Dos verbos y no cinco: `ver` y `gestionar`. Partir la escritura en crear,
 * editar y borrar suena más fino y en la práctica nadie sabe a quién darle sólo
 * «editar», así que se acaba dando todo. Lo que sí se separa es lo que tiene
 * consecuencias distintas.
 *
 * `sistemas.valorar` está aparte de `sistemas.gestionar` a propósito: cambiar el
 * nombre de un sistema no le cambia lo que se le exige, y valorar sus cinco
 * dimensiones sí. Es la decisión que redefine el alcance del cumplimiento
 * entero, y no es la misma persona quien la toma.
 *
 * OJO: esto NO son los roles ENS de personas —responsable de la información,
 * del servicio, de seguridad, del sistema y administrador de la seguridad—, que
 * son datos del módulo de personas (§ 4.8) y tienen sus propias
 * incompatibilidades. Aquí se decide quién puede tocar qué en la herramienta.
 */
#[TypeScript]
enum Permiso: string
{
    case PanelVer = 'panel.ver';

    case SistemasVer = 'sistemas.ver';
    case SistemasGestionar = 'sistemas.gestionar';
    case SistemasValorar = 'sistemas.valorar';

    case ImplantacionesVer = 'implantaciones.ver';
    case ImplantacionesGestionar = 'implantaciones.gestionar';

    case EvidenciasVer = 'evidencias.ver';
    case EvidenciasGestionar = 'evidencias.gestionar';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PanelVer => 'Ver el panel',
            self::SistemasVer => 'Ver los sistemas',
            self::SistemasGestionar => 'Dar de alta y editar sistemas',
            self::SistemasValorar => 'Valorar dimensiones y recalcular',
            self::ImplantacionesVer => 'Ver las implantaciones',
            self::ImplantacionesGestionar => 'Gestionar implantaciones',
            self::EvidenciasVer => 'Ver las evidencias',
            self::EvidenciasGestionar => 'Registrar y vincular evidencias',
        };
    }

    /** Si el permiso escribe. Lo usa la exigencia de segundo factor. */
    public function esDeEscritura(): bool
    {
        return ! str_ends_with($this->value, '.ver');
    }

    /** @return list<self> */
    public static function deEscritura(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $permiso): bool => $permiso->esDeEscritura(),
        ));
    }
}
