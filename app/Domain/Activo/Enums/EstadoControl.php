<?php

declare(strict_types=1);

namespace App\Domain\Activo\Enums;

/**
 * El estado de un control sobre un activo: cifrado en reposo, copia de
 * seguridad. Lo comparten los dos porque se contestan igual y se leen igual.
 *
 * **`PorConfirmar` no es `No`, y ahí está todo el valor de este enum.** Un
 * export de AWS informa del cifrado de los volúmenes pero no dice nada sobre las
 * copias de las instancias EC2; con tres valores, esas instancias figuran como
 * incumplimiento y alguien se pasa una semana «arreglando» copias que ya
 * existían. La ausencia de dato no es ausencia de control: es una pregunta
 * abierta, y se cuenta aparte.
 *
 * `NoAplica` tampoco es `No`: un router no cifra en reposo porque no almacena
 * nada, y contarlo como desprotegido ensucia la cifra que sí importa.
 */
enum EstadoControl: string
{
    case Si = 'si';
    case No = 'no';
    case PorConfirmar = 'por_confirmar';
    case NoAplica = 'no_aplica';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Si => 'Sí',
            self::No => 'No',
            self::PorConfirmar => 'Por confirmar',
            self::NoAplica => 'No aplica',
        };
    }

    /**
     * El icono con el que se reconoce sin leer la etiqueta.
     *
     * «Por confirmar» lleva interrogación y no un aviso: es una pregunta
     * abierta, no un incumplimiento. Es la distinción que justifica que este
     * enum tenga cuatro casos y no tres.
     */
    public function icono(): string
    {
        return match ($this) {
            self::Si => 'Check',
            self::No => 'X',
            self::PorConfirmar => 'CircleHelp',
            self::NoAplica => 'Minus',
        };
    }

    /** El tono del badge. Sólo `No` es rojo: lo demás no va mal, falta saberlo. */
    public function tono(): string
    {
        return match ($this) {
            self::Si => 'implantado',
            self::No => 'caducada',
            self::PorConfirmar => 'en_progreso',
            self::NoAplica => 'no_aplica',
        };
    }

    /** Si cuenta como incumplimiento. Sólo `No`. */
    public function incumple(): bool
    {
        return $this === self::No;
    }
}
