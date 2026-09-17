<?php

declare(strict_types=1);

namespace App\Domain\NoConformidad\Enums;

/**
 * De dónde sale una no conformidad.
 *
 * Mismo criterio que `OrigenTarea`: se declara entero y se van cableando según
 * llegan sus módulos. Hoy existen `Auditoria` —los hallazgos del § 4.12— y
 * `Propia`; incidente y revisión por la dirección esperan a § 4.10 y § 4.15, y
 * **se declaran pero no se ofrecen**, porque una no conformidad marcada como «de
 * un incidente» sin incidente detrás no es trazable, es una etiqueta.
 *
 * `Propia` es la detección fuera de todo proceso formal —alguien se da cuenta de
 * que un procedimiento no se está siguiendo— y no está en § 2.2. Va por el mismo
 * argumento que `OrigenTarea::Propia`: sin un valor para eso, quien la registra
 * elige el que menos mal le suena y el campo deja de significar nada.
 *
 * **Sólo `Auditoria` lleva hallazgo detrás**, y lo impone la base:
 * `no_conformidades_hallazgo_origen_check`. En una sola dirección, porque una
 * auditoría que no está registrada en Statera —la del cliente que la trae en
 * papel— también produce no conformidades de origen auditoría.
 */
enum OrigenNoConformidad: string
{
    case Auditoria = 'auditoria';
    case Incidente = 'incidente';
    case RevisionDireccion = 'revision_direccion';
    case Propia = 'propia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Auditoria => 'Auditoría',
            self::Incidente => 'Incidente',
            self::RevisionDireccion => 'Revisión por la dirección',
            self::Propia => 'Detección propia',
        };
    }

    /**
     * Si hoy se puede registrar una no conformidad con este origen.
     *
     * `match` exhaustivo y no una comparación con `||`, por lo mismo que en
     * `OrigenTarea`: es el único sitio del enum donde olvidarse de un caso nuevo
     * no lo señala nadie.
     */
    public function disponible(): bool
    {
        return match ($this) {
            self::Auditoria, self::Propia => true,
            self::Incidente, self::RevisionDireccion => false,
        };
    }

    /** @return list<self> */
    public static function disponibles(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $origen): bool => $origen->disponible()));
    }
}
