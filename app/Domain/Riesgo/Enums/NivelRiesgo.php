<?php

declare(strict_types=1);

namespace App\Domain\Riesgo\Enums;

/**
 * Cómo de grande es un riesgo, en cinco escalones.
 *
 * **Los escalones no son quintiles de la escala: salen de los umbrales que
 * declara la organización.** `CalculoRiesgo::nivel()` los reparte así — `MuyAlto`
 * es estar en o por encima del umbral crítico, `Alto` es estar por encima del de
 * aceptación sin llegar al crítico, y los tres de abajo reparten en tercios la
 * zona aceptable.
 *
 * Eso es lo que hace que el nivel y el umbral **no puedan contradecirse**. Con
 * bandas por quintiles, un riesgo podía salir «muy alto» estando dentro del
 * apetito declarado —badge rojo en algo que la organización acepta— o al revés, y
 * entonces la tabla y el indicador del panel dirían cosas distintas sobre la
 * misma fila. Aquí `porEncimaDelUmbral()` es exactamente `nivel >= Alto`, por
 * construcción y no por coincidencia.
 *
 * De paso, el número deja de necesitar interpretación: un 12 no significa nada
 * por sí solo, y «por encima de vuestro umbral de aceptación, que está en 10» sí.
 */
enum NivelRiesgo: string
{
    case MuyBajo = 'muy_bajo';
    case Bajo = 'bajo';
    case Medio = 'medio';
    case Alto = 'alto';
    case MuyAlto = 'muy_alto';

    /** Para comparar y ordenar. No es el valor del riesgo: es la posición del escalón. */
    public function peso(): int
    {
        return match ($this) {
            self::MuyBajo => 1,
            self::Bajo => 2,
            self::Medio => 3,
            self::Alto => 4,
            self::MuyAlto => 5,
        };
    }

    /** Si está por encima del umbral de aceptación de la organización. */
    public function sobreUmbral(): bool
    {
        return $this->peso() >= self::Alto->peso();
    }

    /** Si está en o por encima del umbral crítico. */
    public function esCritico(): bool
    {
        return $this === self::MuyAlto;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::MuyBajo => 'Muy bajo',
            self::Bajo => 'Bajo',
            self::Medio => 'Medio',
            self::Alto => 'Alto',
            self::MuyAlto => 'Muy alto',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::MuyBajo => 'ShieldCheck',
            self::Bajo => 'Shield',
            self::Medio => 'ShieldAlert',
            self::Alto => 'TriangleAlert',
            self::MuyAlto => 'OctagonAlert',
        };
    }

    /**
     * El tono del dominio con el que se pinta.
     *
     * Se reutiliza el vocabulario de `--estado-*` en vez de estrenar una familia
     * `--riesgo-*`: cinco colores nuevos habría que medirlos en contraste y en
     * distancia con protanopía —lo que hace `tests/Unit/Diseno/PaletaTest.php`— y
     * el hueco de color libre ya está repartido entre los estados y los nueve
     * tipos de activo. Lo que se lee aquí es el **orden**, y el orden se lee por
     * énfasis creciente, que es lo que DESIGN.md §3 dice de lo ordinal.
     *
     * **`MuyAlto` sí gasta el rojo, y es el tercer sitio que lo hace.** Los otros
     * dos son una evidencia caducada y una tarea fuera de plazo, y el criterio de
     * DESIGN.md es el mismo: rojo para lo que va mal de verdad, no para lo que es
     * grande. Aquí va mal de verdad porque `MuyAlto` **es**, por construcción,
     * estar por encima del umbral crítico que puso la propia organización — no un
     * juicio de la herramienta sobre si el número le parece alto.
     */
    public function tono(): string
    {
        return match ($this) {
            self::MuyBajo => 'no_aplica',
            self::Bajo => 'no_iniciado',
            self::Medio => 'planificado',
            self::Alto => 'en_progreso',
            self::MuyAlto => 'caducada',
        };
    }
}
