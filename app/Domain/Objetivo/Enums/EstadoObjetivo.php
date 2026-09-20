<?php

declare(strict_types=1);

namespace App\Domain\Objetivo\Enums;

/**
 * El ciclo de un objetivo de seguridad, con un estado por cada cosa que la
 * cláusula 6.2 distingue.
 *
 * `Propuesto` es «está escrito y la dirección todavía no lo ha firmado»;
 * `Aprobado`, que la organización se ha comprometido y el reloj corre;
 * `Alcanzado` y `NoAlcanzado`, cómo acabó al llegar el plazo; y `Retirado`, que
 * se dejó de perseguir.
 *
 * **`Alcanzado` y `NoAlcanzado` son dos estados y no un `resultado` al lado de un
 * `cerrado`**, por lo mismo que `Verificada` es un estado en una no conformidad:
 * «cuántos de los objetivos del año se alcanzaron» es literalmente una de las
 * siete entradas de la revisión por la dirección, y con el resultado en otra
 * columna esa cifra dependería de cruzar dos campos que pueden desincronizarse.
 *
 * **`NoAlcanzado` no es un fracaso del que haya que esconderse, y por eso no gasta
 * rojo.** Es el mismo argumento que dejó sin rojo los cuatro veredictos del
 * § 4.14: quedarse corto respecto a una cifra que la propia organización se puso
 * es la distancia que queda, y pintarlo de alarma castiga por ponerse objetivos
 * ambiciosos — que es exactamente lo que el quinto principio del producto existe
 * para impedir. Lo que sí exige es **decir por qué**, que es lo que la 9.3 va a
 * preguntar.
 *
 * `Retirado` es el objetivo que se deja de perseguir: cambió el contexto, o
 * cambió la prioridad. **Exige motivo** igual que `descartada` en tareas,
 * `anulada` en una no conformidad y `rechazado` en un documento, y no se borra la
 * fila: borrarla dejaría el año sin rastro de qué se decidió.
 */
enum EstadoObjetivo: string
{
    case Propuesto = 'propuesto';
    case Aprobado = 'aprobado';
    case Alcanzado = 'alcanzado';
    case NoAlcanzado = 'no_alcanzado';
    case Retirado = 'retirado';

    /**
     * A qué estados se puede pasar desde éste.
     *
     * De los dos cierres con resultado se vuelve a `Aprobado`, que es la puerta de
     * siempre: un objetivo que se dio por no alcanzado en diciembre y al que la
     * dirección decide dar otro trimestre se reabre con su transición, su fecha y
     * su autor. Lo que no se puede es volver a `Propuesto` desde algo aprobado:
     * decir que un compromiso firmado está sin firmar es reescribir el pasado,
     * igual que devolver una auditoría a `planificada`.
     *
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::Propuesto => [self::Aprobado, self::Retirado],
            self::Aprobado => [self::Alcanzado, self::NoAlcanzado, self::Retirado],
            self::Alcanzado, self::NoAlcanzado => [self::Aprobado, self::Retirado],
            // Se retiró por error, o volvió a hacer falta: vuelve al borrador.
            self::Retirado => [self::Propuesto],
        };
    }

    public function permite(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }

    /**
     * Si la dirección ya se ha comprometido con él.
     *
     * Son los tres estados a los que la base exige plazo y firma, y la razón está
     * en la propia 6.2: un objetivo aprobado sin decir para cuándo ni quién
     * responde es una consigna, no un objetivo.
     */
    public function esComprometido(): bool
    {
        return $this === self::Aprobado || $this === self::Alcanzado || $this === self::NoAlcanzado;
    }

    /**
     * Si ha dejado de estar vivo.
     *
     * Mismo reparto que `EstadoNoConformidad::esCerrada()`, y el que decide qué
     * cuenta como objetivo en curso: uno retirado no está pendiente, está cerrado
     * con su motivo en el histórico.
     */
    public function esCerrado(): bool
    {
        return $this === self::Alcanzado || $this === self::NoAlcanzado || $this === self::Retirado;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Propuesto => 'Propuesto',
            self::Aprobado => 'Aprobado',
            self::Alcanzado => 'Alcanzado',
            self::NoAlcanzado => 'No alcanzado',
            self::Retirado => 'Retirado',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            // Escrito y esperando firma.
            self::Propuesto => 'PenLine',
            // Firmado y en marcha.
            self::Aprobado => 'CircleDotDashed',
            self::Alcanzado => 'CircleCheck',
            self::NoAlcanzado => 'CircleX',
            self::Retirado => 'Archive',
        };
    }

    /**
     * El vocabulario de siempre, con dos decisiones que conviene declarar.
     *
     * **`Propuesto` gasta el violeta de `en_revision`, y es el tercer badge que lo
     * hace.** Los otros dos son la versión de un documento esperando firma y la no
     * conformidad tratada y pendiente de verificar, y los tres significan
     * exactamente lo mismo: hecho y a la espera de que alguien con potestad lo
     * confirme. No abre un quinto sitio para el violeta —el token ya era uno de
     * los cuatro—, y DESIGN.md se lo reserva literalmente a los flujos de revisión.
     *
     * **`NoAlcanzado` va en el gris neutro y no en rojo.** Ver la cabecera: el
     * rojo de este módulo no es quedarse corto, y de hecho el módulo no gasta
     * ninguno en el estado. Lo que sí va en rojo es la columna «Plazo» cuando un
     * objetivo aprobado se pasa de fecha sin cerrarse, que es el mismo reparto que
     * en tareas y en no conformidades: el rojo es del plazo, no del estado.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Propuesto => 'en_revision',
            self::Aprobado => 'en_progreso',
            self::Alcanzado => 'implantado',
            self::NoAlcanzado => 'no_iniciado',
            self::Retirado => 'no_aplica',
        };
    }
}
