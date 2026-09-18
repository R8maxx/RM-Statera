<?php

declare(strict_types=1);

namespace App\Domain\Contexto\Enums;

/**
 * Los cuatro cuadrantes del DAFO, que son la cláusula 4.1 de ISO.
 *
 * **Es el único campo del módulo del que cuelga todo lo demás.** El ámbito
 * —interno o externo— y el signo —a favor o en contra— no son columnas: se
 * derivan de aquí, porque eso es lo que define un DAFO y guardarlo aparte sería
 * la misma información en tres sitios que pueden discrepar. Reclasificar una
 * cuestión es cambiar un campo, no tres.
 *
 * ### Un tono por cuadrante, en familia propia
 *
 * Los cuatro cuadrantes tienen su propio color, en la familia `--dafo-*` que
 * `DESIGN.md` § 3 declara. No se reutiliza ninguna de las otras dos familias
 * semánticas: un estado dice *cómo va* algo, un tipo dice *qué es* y un cuadrante
 * dice *dónde cae*, y compartir paleta haría que una fortaleza se leyera como
 * «implantado».
 *
 * **Lo que separa a la familia no es el hue, es la profundidad**: L 0.40 y croma
 * 0.16, frente a L 0.52 / 0.13 de los estados y L 0.47 / 0.10 de los tipos. Los
 * nueve `--tipo-*` ocupan ya la rueda entera, así que el hue solo no daba para una
 * tercera familia y hubo que bajar la luminosidad.
 *
 * **Los hues van por pares y no sueltos**, que es lo que hace legible un 2×2:
 * verde 175 y azul 255 son lo favorable —dentro y fuera— y ocre 55 y magenta 340
 * lo adverso. Así el color dice las dos cosas a la vez: de qué mitad es por la
 * familia cromática, y qué cuadrante exacto por el tono.
 *
 * **Ninguno entra en el rojo**, que sigue teniendo sus tres dueños —evidencia
 * caducada, tarea vencida y `NivelRiesgo::MuyAlto`—. Una debilidad apuntada en un
 * análisis del contexto no va mal: es algo que la organización ha sabido ver y ha
 * escrito, y pintarlo de alarma enseña a no escribirlo.
 *
 * **Y el icono sigue sin ser opcional.** ΔE 10.7 en el peor par de la familia es
 * el mejor de las tres, pero contra `destructive` la peor pareja queda en 2.7 con
 * protanopía: un verde oscuro y un rojo colapsan sobre el mismo eje. El color
 * agrupa y el icono identifica, que es la regla entera de `DESIGN.md` § 3.
 */
enum TipoCuestion: string
{
    case Fortaleza = 'fortaleza';
    case Debilidad = 'debilidad';
    case Oportunidad = 'oportunidad';
    case Amenaza = 'amenaza';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Fortaleza => 'Fortaleza',
            self::Debilidad => 'Debilidad',
            self::Oportunidad => 'Oportunidad',
            self::Amenaza => 'Amenaza',
        };
    }

    /** Si la cuestión es de la organización o de su entorno. Derivado, no guardado. */
    public function ambito(): Ambito
    {
        return match ($this) {
            self::Fortaleza, self::Debilidad => Ambito::Interno,
            self::Oportunidad, self::Amenaza => Ambito::Externo,
        };
    }

    /** Si juega a favor o en contra. Derivado, no guardado. */
    public function signo(): Signo
    {
        return match ($this) {
            self::Fortaleza, self::Oportunidad => Signo::Favorable,
            self::Debilidad, self::Amenaza => Signo::Adverso,
        };
    }

    /**
     * Los cuatro, en el orden en que se leen en la matriz.
     *
     * Interno arriba y externo abajo, favorable a la izquierda: es el orden
     * clásico del DAFO y el que la gente espera encontrarse. Lo consumen la
     * rejilla y el reparto del panel, para que los dos cuenten en el mismo orden.
     *
     * @return list<self>
     */
    public static function enOrdenDeMatriz(): array
    {
        return [self::Fortaleza, self::Debilidad, self::Oportunidad, self::Amenaza];
    }

    /**
     * Los de un cuadrante de la matriz.
     *
     * @return list<self>
     */
    public static function deAmbito(Ambito $ambito): array
    {
        return array_values(array_filter(
            self::enOrdenDeMatriz(),
            static fn (self $tipo): bool => $tipo->ambito() === $ambito,
        ));
    }

    public function icono(): string
    {
        return match ($this) {
            // El escudo con el visto: algo que ya protege.
            self::Fortaleza => 'ShieldCheck',
            // El mismo escudo con el aviso: la defensa que falta, no un fallo.
            self::Debilidad => 'ShieldAlert',
            self::Oportunidad => 'Lightbulb',
            // Viene de fuera y no se controla, como el tiempo.
            self::Amenaza => 'CloudLightning',
        };
    }

    /** Ver la explicación larga de la cabecera: un tono por cuadrante, familia propia. */
    public function tono(): string
    {
        return match ($this) {
            self::Fortaleza => 'dafo:fortaleza',
            self::Debilidad => 'dafo:debilidad',
            self::Oportunidad => 'dafo:oportunidad',
            self::Amenaza => 'dafo:amenaza',
        };
    }
}
