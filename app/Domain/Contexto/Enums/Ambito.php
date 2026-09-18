<?php

declare(strict_types=1);

namespace App\Domain\Contexto\Enums;

/**
 * Dentro o fuera de la organización.
 *
 * **Un solo enum para las dos cláusulas**, y no es ahorro: la 4.1 habla de
 * «cuestiones internas y externas» y la 4.2 de partes interesadas que también lo
 * son, y es literalmente el mismo eje. Con dos enums, el día que alguien quisiera
 * contestar «¿qué tenemos de fuera, cuestiones y partes juntas?» tendría que
 * cruzar dos vocabularios que dicen lo mismo con palabras distintas.
 *
 * Lo que cambia es **cómo se rellena**, y esa asimetría sí es real:
 *
 * - En una cuestión **se deriva** del tipo. Una fortaleza es interna y una amenaza
 *   externa por definición del DAFO; guardarlo sería el mismo dato en dos columnas
 *   que pueden discrepar. Lo devuelve `TipoCuestion::ambito()`.
 * - En una parte interesada **se guarda**, porque no se puede deducir del tipo: un
 *   empleado es interno y un regulador externo, pero un socio o un accionista son
 *   lo que cada organización decida que son. Deducirlo acertaría en seis de ocho,
 *   que es la peor cifra posible — suficiente para que parezca que funciona.
 */
enum Ambito: string
{
    case Interno = 'interno';
    case Externo = 'externo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Interno => 'Interno',
            self::Externo => 'Externo',
        };
    }

    /** La explicación que acompaña al rótulo del eje en la matriz del DAFO. */
    public function ayuda(): string
    {
        return match ($this) {
            self::Interno => 'De la organización: lo que está en su mano cambiar.',
            self::Externo => 'Del entorno: lo que hay que aprovechar o resistir.',
        };
    }
}
