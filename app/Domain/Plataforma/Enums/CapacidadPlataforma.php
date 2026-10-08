<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Lo que se puede hacer en la plataforma (punto 48).
 *
 * No son permisos de spatie: aquéllos viven en el «team» de una organización,
 * y la plataforma no es ninguna. Cada ruta de `/plataforma` exige una con el
 * middleware `plataforma:<capacidad>`, y el perfil del administrador decide
 * cuáles tiene (`PerfilPlataforma::capacidades()`).
 *
 * El cliente las recibe en `auth.permisos` con el prefijo `plataforma.`, sólo
 * para decidir qué pinta.
 */
#[TypeScript]
enum CapacidadPlataforma: string
{
    case ClientesVer = 'clientes.ver';
    case ClientesGestionar = 'clientes.gestionar';
    case PlanesGestionar = 'planes.gestionar';
    case SoporteEntrar = 'soporte.entrar';
    case CuentasRescatar = 'cuentas.rescatar';
    case ClientesBaja = 'clientes.baja';
    case ClientesExportar = 'clientes.exportar';
    case AdministradoresGestionar = 'administradores.gestionar';
    case TrazaVer = 'traza.ver';
    case SaludVer = 'salud.ver';

    /** Como viaja al cliente en `auth.permisos`. */
    public function permiso(): string
    {
        return 'plataforma.'.$this->value;
    }
}
