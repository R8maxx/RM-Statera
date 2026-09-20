<?php

declare(strict_types=1);

namespace App\Domain\Mejora\Enums;

/**
 * El ciclo de una oportunidad de mejora. Cláusula 10.1.
 *
 * **Cuatro estados y no cinco**, y la diferencia con `EstadoNoConformidad` es la
 * que hace la norma: allí hay un quinto —`Verificada`— porque la 10.2 e) pide
 * comprobar que la corrección **sirvió**, y aquí no hay nada que verificar porque
 * no había nada roto. Copiar ese estado «por simetría» pondría en el registro un
 * hueco vacío que nadie puede rellenar, y un hueco que no se puede rellenar se
 * lee como trabajo pendiente.
 *
 * `Propuesta` es «se le ha ocurrido a alguien y está apuntada»; `EnCurso`, que hay
 * algo en marcha; `Implantada`, que se hizo; y `Descartada`, que se decidió no
 * hacerla.
 *
 * **`Descartada` exige motivo**, igual que en tareas, en no conformidades y en
 * objetivos, y no se borra la fila: borrarla dejaría el hallazgo —o la revisión
 * por la dirección de la que salió— sin rastro de qué se decidió. Y es el estado
 * que más se va a usar: la mayoría de las ideas que se apuntan no se hacen, y eso
 * no es un problema, es lo que la 10.1 espera de un registro vivo.
 */
enum EstadoMejora: string
{
    case Propuesta = 'propuesta';
    case EnCurso = 'en_curso';
    case Implantada = 'implantada';
    case Descartada = 'descartada';

    /**
     * A qué estados se puede pasar desde éste.
     *
     * De `Implantada` se vuelve a `EnCurso` —se dio por hecha antes de tiempo— y
     * de `Descartada` a `Propuesta` —se descartó y luego volvió a hacer falta—.
     * Las dos son la puerta de siempre: corregir se puede, a escondidas no.
     *
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::Propuesta => [self::EnCurso, self::Implantada, self::Descartada],
            self::EnCurso => [self::Implantada, self::Descartada],
            self::Implantada => [self::EnCurso],
            self::Descartada => [self::Propuesta],
        };
    }

    public function permite(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }

    /**
     * Si ha dejado de estar abierta.
     *
     * Mismo reparto que `EstadoNoConformidad::esCerrada()` y
     * `EstadoObjetivo::esCerrado()`: una descartada no está pendiente, está
     * cerrada con su motivo en el histórico.
     */
    public function esCerrada(): bool
    {
        return $this === self::Implantada || $this === self::Descartada;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Propuesta => 'Propuesta',
            self::EnCurso => 'En curso',
            self::Implantada => 'Implantada',
            self::Descartada => 'Descartada',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            // La bombilla es la del tipo de hallazgo del que muchas vienen.
            self::Propuesta => 'Lightbulb',
            self::EnCurso => 'Wrench',
            self::Implantada => 'CircleCheck',
            self::Descartada => 'CircleSlash',
        };
    }

    /**
     * **Ninguno gasta rojo, y este módulo no tiene ningún rojo en ninguna
     * columna.** Es el único del producto del que se puede decir eso, y es
     * deliberado: una mejora que no se ha hecho no incumple nada —la 10.1 pide
     * mejorar de forma continua, no tener cero ideas pendientes— y una mejora
     * descartada es una decisión legítima. Pintar de rojo lo que alguien apuntó
     * voluntariamente es la forma más rápida de que deje de apuntarlo, que es el
     * quinto principio del producto.
     *
     * Ni siquiera el plazo: una mejora con la fecha pasada sigue siendo una idea
     * abierta y no un compromiso incumplido. Lo que sí se señala —en gris— es que
     * se pasó de fecha.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Propuesta => 'planificado',
            self::EnCurso => 'en_progreso',
            self::Implantada => 'implantado',
            self::Descartada => 'no_aplica',
        };
    }
}
