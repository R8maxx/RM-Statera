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
     * Tampoco aprueba documentos el técnico, y desde el § 4.5 eso es lo mismo
     * que decir que no los entrega: aprobar es lo que numera la versión, congela
     * el PDF y lo mueve a `emitidas/`. Lo que sí hace es redactarlos y, cuando
     * los da por terminados, **mandarlos a revisión** —que va con `redactar` y no
     * con `generar`, porque es el final de escribir y no el principio de
     * entregar—. Leerlos sí, los dos: un auditor que no pudiera abrir la
     * Declaración de Aplicabilidad no podría auditar nada.
     *
     * Redactar los textos de un documento sí lo hace el técnico —es trabajo de
     * quien lo está preparando—, pero la plantilla de la organización no: eso
     * decide cómo van a empezar todos los documentos futuros.
     *
     * Las tareas las gestiona el técnico entero: apuntar lo que hay que hacer,
     * cogerlo y cerrarlo es su trabajo diario, y un plan de acción que sólo pueda
     * tocar el responsable de seguridad se queda sin actualizar a la semana.
     *
     * Los riesgos los registra y los valora el técnico, y **no los acepta**: quien
     * conoce la amenaza y sabe qué salvaguardas hay puestas es quien mejor la mide,
     * pero decidir que la organización convive con una exposición es de dirección.
     * Es la misma línea que separa redactar un documento de emitirlo.
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
                Permiso::RiesgosVer,
                Permiso::RiesgosGestionar,
                Permiso::TareasVer,
                Permiso::TareasGestionar,
                Permiso::DocumentosVer,
                Permiso::DocumentosRedactar,
            ],

            self::Auditor => [
                Permiso::PanelVer,
                Permiso::SistemasVer,
                Permiso::ImplantacionesVer,
                Permiso::EvidenciasVer,
                Permiso::ActivosVer,
                Permiso::RiesgosVer,
                Permiso::TareasVer,
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
