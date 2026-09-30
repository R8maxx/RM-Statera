<?php

declare(strict_types=1);

namespace App\Domain\Cambio\Enums;

/**
 * Qué parte del SGSI cambia.
 *
 * **Sólo el sistema de gestión**, y es la frontera del módulo: un cambio técnico
 * —un parche, una migración de servidor— es A.8.32 y `op.exp.5`, que tienen otro
 * ritmo y no se registran aquí. `Otro` existe porque la lista no puede prever
 * todo, no para colar por él lo que es técnico.
 */
enum AmbitoCambio: string
{
    case Alcance = 'alcance';
    case Politica = 'politica';
    case Organizacion = 'organizacion';
    case Proceso = 'proceso';
    case Recursos = 'recursos';
    case Documentacion = 'documentacion';
    case Otro = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Alcance => 'Alcance del SGSI',
            self::Politica => 'Política de seguridad',
            self::Organizacion => 'Organización y roles',
            self::Proceso => 'Proceso del sistema de gestión',
            self::Recursos => 'Recursos',
            self::Documentacion => 'Documentación',
            self::Otro => 'Otro',
        };
    }
}
