<?php

declare(strict_types=1);

namespace App\Domain\NoConformidad\Enums;

/**
 * De dónde sale una no conformidad.
 *
 * Mismo criterio que `OrigenTarea`: se declara entero y se van cableando según
 * llegan sus módulos. **Desde el § 4.10 se ofrecen los cuatro.**
 *
 * `Incidente` entra con su módulo y con su clave foránea —`incidente_id`, espejo
 * exacto de `hallazgo_id`—, así que es trazable de verdad y no una etiqueta.
 * `RevisionDireccion` se ofrece **sin clave foránea**, y eso es un reparto
 * distinto y deliberado: no la tiene porque una revisión por la dirección no
 * «produce» no conformidades del sistema de gestión con la misma mecánica que una
 * auditoría —sus salidas son decisiones, y ésas ya son tareas—, pero el origen es
 * un hecho que alguien declara y esconderlo obligaba a marcarlo como `propia`.
 * **Llevaba en `false` desde que el § 4.15 se construyó**: una frase que envejeció
 * en el tramo anterior.
 *
 * `Propia` es la detección fuera de todo proceso formal —alguien se da cuenta de
 * que un procedimiento no se está siguiendo— y no está en § 2.2. Va por el mismo
 * argumento que `OrigenTarea::Propia`: sin un valor para eso, quien la registra
 * elige el que menos mal le suena y el campo deja de significar nada.
 *
 * **Sólo `Auditoria` lleva hallazgo detrás y sólo `Incidente` lleva incidente**,
 * y lo imponen la base —`no_conformidades_hallazgo_origen_check` y su gemelo— en
 * una sola dirección: una auditoría que no está registrada en Statera —la del
 * cliente que la trae en papel— también produce no conformidades de origen
 * auditoría. Y un tercer `CHECK` impide que vengan de las dos cosas a la vez.
 *
 * **`PruebaContinuidad` es el quinto, y llega con el § 4.11.** Lleva
 * `prueba_continuidad_id` detrás, espejo exacto de `incidente_id`: una prueba de
 * un plan de continuidad que sale parcial o fallida puede destapar que la
 * organización no cumplía lo que su propio plan prometía —`op.cont.3`—, y eso se
 * trata aquí igual que cualquier otro incumplimiento. `num_nonnulls()` sustituye
 * al `CHECK` de dos columnas que bastaba con `hallazgo_id` e `incidente_id`: con
 * tres procedencias posibles, «como mucho una» ya no se escribe con un `OR IS
 * NULL`.
 */
enum OrigenNoConformidad: string
{
    case Auditoria = 'auditoria';
    case Incidente = 'incidente';
    case RevisionDireccion = 'revision_direccion';
    case PruebaContinuidad = 'prueba_continuidad';
    case Propia = 'propia';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Auditoria => 'Auditoría',
            self::Incidente => 'Incidente',
            self::RevisionDireccion => 'Revisión por la dirección',
            self::PruebaContinuidad => 'Prueba de continuidad',
            self::Propia => 'Detección propia',
        };
    }

    /**
     * Si hoy se puede registrar una no conformidad con este origen.
     *
     * `match` exhaustivo y no un `return true`, por lo mismo que en `OrigenTarea`:
     * hoy están los cinco, y el día que entre uno más cuyo módulo no exista,
     * olvidarse de él aquí no lo señalaría nadie.
     */
    public function disponible(): bool
    {
        return match ($this) {
            self::Auditoria, self::Incidente, self::RevisionDireccion,
            self::PruebaContinuidad, self::Propia => true,
        };
    }

    /** @return list<self> */
    public static function disponibles(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $origen): bool => $origen->disponible()));
    }
}
