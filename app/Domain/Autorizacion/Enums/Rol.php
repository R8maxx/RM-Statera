<?php

declare(strict_types=1);

namespace App\Domain\Autorizacion\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Los tres roles de partida del § 4.19.
 *
 * Son roles POR ORGANIZACIÓN: `spatie/laravel-permission` va con `teams = true`
 * y `team_foreign_key = organizacion_id`. Un responsable de seguridad lo es de
 * la suya y de ninguna otra, y el aislamiento sigue mandando por encima del
 * permiso: ningún rol atraviesa la frontera del tenant.
 */
#[TypeScript]
enum Rol: string
{
    case ResponsableSeguridad = 'responsable_seguridad';
    case Tecnico = 'tecnico';
    case Auditor = 'auditor';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ResponsableSeguridad => 'Responsable de seguridad',
            self::Tecnico => 'Técnico',
            self::Auditor => 'Auditor',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::ResponsableSeguridad => 'Responde del SGSI: valora los sistemas, decide el alcance y firma lo que se entrega.',
            self::Tecnico => 'Implanta y prueba: mueve estados, sube evidencias y las vincula. No redefine el alcance.',
            self::Auditor => 'Sólo lectura. Ve el cumplimiento y sus pruebas, y no puede alterar nada de lo que audita.',
        };
    }

    /**
     * El técnico no valora ni da de alta sistemas: eso redefine lo que se le
     * exige a la organización, y quien lo hace responde de ello. El auditor no
     * escribe nada, porque alterar lo que audita invalida la auditoría.
     *
     * Tampoco emite documentos el técnico: emitir una versión es entregarla, y
     * quien la firma es el responsable de seguridad. Leerlos sí, los dos: un
     * auditor que no pudiera abrir la Declaración de Aplicabilidad no podría
     * auditar nada.
     *
     * Redactar los textos de un documento sí lo hace el técnico —es trabajo de
     * quien lo está preparando—, pero la plantilla de la organización no: eso
     * decide cómo van a empezar todos los documentos futuros.
     *
     * @return list<Permiso>
     */
    public function permisos(): array
    {
        return match ($this) {
            self::ResponsableSeguridad => Permiso::cases(),

            self::Tecnico => [
                Permiso::PanelVer,
                Permiso::SistemasVer,
                Permiso::ImplantacionesVer,
                Permiso::ImplantacionesGestionar,
                Permiso::EvidenciasVer,
                Permiso::EvidenciasGestionar,
                Permiso::ActivosVer,
                Permiso::ActivosGestionar,
                Permiso::DocumentosVer,
                Permiso::DocumentosRedactar,
            ],

            self::Auditor => [
                Permiso::PanelVer,
                Permiso::SistemasVer,
                Permiso::ImplantacionesVer,
                Permiso::EvidenciasVer,
                Permiso::ActivosVer,
                Permiso::DocumentosVer,
            ],
        };
    }

    /** Si el rol puede escribir. Lo usa la exigencia de segundo factor. */
    public function escribe(): bool
    {
        foreach ($this->permisos() as $permiso) {
            if ($permiso->esDeEscritura()) {
                return true;
            }
        }

        return false;
    }
}
