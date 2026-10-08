<?php

declare(strict_types=1);

namespace App\Domain\Plataforma\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Con qué perfil administra alguien la plataforma (punto 48).
 *
 * Dos, por decisión de César: mínimo privilegio sin complicarlo.
 *
 * - **Administración** puede todo.
 * - **Gestión comercial** lleva los clientes, los planes y la suscripción. No
 *   entra como soporte, no rescata cuentas, no da de baja, no exporta ni ve la
 *   salud del servicio: son las puertas que tocan datos del cliente o el
 *   servicio entero.
 */
#[TypeScript]
enum PerfilPlataforma: string
{
    case Administracion = 'administracion';
    case Comercial = 'comercial';

    /** @return list<CapacidadPlataforma> */
    public function capacidades(): array
    {
        return match ($this) {
            self::Administracion => CapacidadPlataforma::cases(),
            self::Comercial => [
                CapacidadPlataforma::ClientesVer,
                CapacidadPlataforma::ClientesGestionar,
                CapacidadPlataforma::PlanesGestionar,
                CapacidadPlataforma::TrazaVer,
            ],
        };
    }

    public function puede(CapacidadPlataforma $capacidad): bool
    {
        return in_array($capacidad, $this->capacidades(), true);
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Administracion => 'Administración',
            self::Comercial => 'Gestión comercial',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Administracion => 'Todo: clientes, planes, soporte, rescate de cuentas, bajas, exportaciones, administradores y salud del servicio.',
            self::Comercial => 'Clientes, planes y suscripciones. Ni soporte, ni rescate de cuentas, ni bajas, ni exportaciones, ni salud del servicio.',
        };
    }
}
