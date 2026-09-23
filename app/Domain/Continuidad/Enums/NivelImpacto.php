<?php

declare(strict_types=1);

namespace App\Domain\Continuidad\Enums;

/**
 * Cómo de grave es no tener un servicio disponible a un horizonte del MTPD.
 *
 * Cuatro escalones y no cinco: a diferencia de `NivelRiesgo`, no hay
 * `muy_bajo` — el BIA no distingue entre «no pasa nada» y «casi no pasa nada»,
 * porque lo único que le importa a `UmbralTolerable` es dónde aparece el
 * primer `muy_alto`. Añadir un quinto escalón no cambiaría esa pregunta y sí
 * complicaría la escala que `bia_servicios_monotonia_check` recorre a mano.
 */
enum NivelImpacto: string
{
    case Bajo = 'bajo';
    case Medio = 'medio';
    case Alto = 'alto';
    case MuyAlto = 'muy_alto';

    /** Para comparar y para el `array_position` que impone la monotonía en SQL. */
    public function peso(): int
    {
        return match ($this) {
            self::Bajo => 1,
            self::Medio => 2,
            self::Alto => 3,
            self::MuyAlto => 4,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Bajo => 'Bajo',
            self::Medio => 'Medio',
            self::Alto => 'Alto',
            self::MuyAlto => 'Muy alto',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Bajo => 'Shield',
            self::Medio => 'ShieldAlert',
            self::Alto => 'TriangleAlert',
            self::MuyAlto => 'OctagonAlert',
        };
    }

    /**
     * El tono, con el mismo orden por énfasis creciente que `NivelRiesgo::tono()`.
     *
     * **Sólo `MuyAlto` gasta el rojo de `caducada`.** Reservado para lo que va
     * mal de verdad, igual que en `NivelRiesgo`: aquí «mal de verdad» es
     * justo el tramo en el que empieza a contar `UmbralTolerable`.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Bajo => 'no_iniciado',
            self::Medio => 'planificado',
            self::Alto => 'en_progreso',
            self::MuyAlto => 'caducada',
        };
    }
}
