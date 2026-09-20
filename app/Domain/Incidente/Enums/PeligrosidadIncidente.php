<?php

declare(strict_types=1);

namespace App\Domain\Incidente\Enums;

/**
 * Los cinco niveles de peligrosidad del CCN-STIC 817.
 *
 * **Y esto sí gasta rojo en su nivel más alto**, a diferencia de los estados: es
 * el mismo caso que `NivelRiesgo::MuyAlto`. Un incidente crítico no es «grande»,
 * es una exposición que ya está ocurriendo y que la propia guía califica así —no
 * un juicio de la herramienta sobre si el número le parece alto—.
 *
 * **La declara una persona y no se calcula.** Sería tentador derivarla de las
 * cinco dimensiones afectadas, y sería una opinión de la herramienta disfrazada
 * de cálculo: la 817 no publica ninguna función de (dimensiones) a peligrosidad,
 * y el mismo compromiso de confidencialidad es crítico en un sistema y bajo en
 * otro. Mismo razonamiento que el riesgo residual, que tampoco se deduce.
 *
 * > Como la clasificación, **no está contrastada celda a celda contra la guía**.
 * > Los cinco niveles y sus nombres sí salen de ella; los criterios de asignación
 * > no están cargados.
 */
enum PeligrosidadIncidente: string
{
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';
    case MuyAlta = 'muy_alta';
    case Critica = 'critica';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Baja => 'Baja',
            self::Media => 'Media',
            self::Alta => 'Alta',
            self::MuyAlta => 'Muy alta',
            self::Critica => 'Crítica',
        };
    }

    /**
     * **Calcado de `NivelRiesgo::tono()`**, escalón a escalón, y no de la familia
     * ordinal `basica`/`media`/`alta`. El motivo: los dos badges conviven en la
     * misma pantalla en cuanto un incidente abre una no conformidad, y que
     * «crítica» y «muy alto» se pinten distinto haría pensar que dicen cosas de
     * gravedad distinta. La familia ordinal es de la categoría del ENS, que es
     * una derivación legal y no una escala de gravedad.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Baja => 'no_aplica',
            self::Media => 'no_iniciado',
            self::Alta => 'planificado',
            self::MuyAlta => 'en_progreso',
            self::Critica => 'caducada',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Baja => 'ChevronDown',
            self::Media => 'Equal',
            self::Alta => 'ChevronUp',
            self::MuyAlta => 'ChevronsUp',
            self::Critica => 'OctagonAlert',
        };
    }
}
