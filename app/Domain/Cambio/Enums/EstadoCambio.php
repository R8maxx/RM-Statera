<?php

declare(strict_types=1);

namespace App\Domain\Cambio\Enums;

/**
 * El ciclo de un cambio del SGSI. Cláusula 6.3.
 *
 * `Propuesto` es «alguien quiere cambiar esto»; `Aprobado`, que la dirección lo
 * ha firmado y hay plazo; `Implantado`, que se hizo; `Revisado`, que se ha
 * comprobado si sirvió; y `Descartado`, que se decidió no hacerlo.
 *
 * **`Implantado` y `Revisado` son dos estados y no uno**, y la diferencia es la
 * pregunta que la 6.3 deja abierta: planificar un cambio incluye decir qué se
 * esperaba de él, y cerrarlo sin mirar si llegó convierte el propósito escrito al
 * principio en un trámite. Mismo argumento que separa `Implantada` de
 * `Verificada` en una no conformidad, sin la verificación de eficacia de la 10.2:
 * aquí no había nada roto.
 */
enum EstadoCambio: string
{
    case Propuesto = 'propuesto';
    case Aprobado = 'aprobado';
    case Implantado = 'implantado';
    case Revisado = 'revisado';
    case Descartado = 'descartado';

    /**
     * A qué estados se puede pasar desde éste.
     *
     * Las vueltas atrás —aprobado a propuesto, implantado a aprobado, revisado a
     * implantado, descartado a propuesto— son la puerta de siempre: corregir se
     * puede, a escondidas no. Todas menos la primera exigen motivo.
     *
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::Propuesto => [self::Aprobado, self::Descartado],
            self::Aprobado => [self::Implantado, self::Propuesto, self::Descartado],
            self::Implantado => [self::Revisado, self::Aprobado],
            self::Revisado => [self::Implantado],
            self::Descartado => [self::Propuesto],
        };
    }

    public function permite(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }

    /**
     * Si el cambio compromete a la organización: firma y plazo obligatorios.
     *
     * Lo mismo que `EstadoObjetivo::esComprometido()`, y lo mismo que dicen los
     * dos `CHECK` de la tabla.
     */
    public function esComprometido(): bool
    {
        return $this === self::Aprobado || $this === self::Implantado || $this === self::Revisado;
    }

    /** Si el cambio ya está hecho, revisado o no. */
    public function estaHecho(): bool
    {
        return $this === self::Implantado || $this === self::Revisado;
    }

    public function esCerrado(): bool
    {
        return $this === self::Revisado || $this === self::Descartado;
    }

    /**
     * Si llegar a este estado exige el verbo de supervisión.
     *
     * Aprobar es comprometer a la organización con un cambio de su sistema de
     * gestión, y **renunciar a uno ya aprobado** también es de quien lo firmó:
     * quien lo redacta no puede deshacer una decisión que no tomó. Descartar una
     * propuesta, en cambio, lo puede hacer quien la escribió.
     */
    public function exigeAprobar(self $desde): bool
    {
        return $this === self::Aprobado
            || ($this === self::Descartado && $desde->esComprometido());
    }

    /**
     * Si llegar a este estado desde `$desde` exige una nota escrita.
     *
     * Descartar —«esto no se hace»—, revisar —«si sirvió», que va a la columna
     * `revision`— y toda vuelta atrás salvo la del borrador, que ya suelta la
     * firma entera y no deja nada que explicar.
     */
    public function exigeNota(self $desde): bool
    {
        return match ($this) {
            self::Descartado, self::Revisado => true,
            self::Aprobado => $desde === self::Implantado,
            self::Implantado => $desde === self::Revisado,
            self::Propuesto => $desde === self::Descartado,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Propuesto => 'Propuesto',
            self::Aprobado => 'Aprobado',
            self::Implantado => 'Implantado',
            self::Revisado => 'Revisado',
            self::Descartado => 'Descartado',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Propuesto => 'PenLine',
            self::Aprobado => 'CircleDotDashed',
            self::Implantado => 'CircleCheck',
            self::Revisado => 'BadgeCheck',
            self::Descartado => 'CircleSlash',
        };
    }

    /**
     * **Ningún estado gasta rojo.** El rojo de este registro es el plazo: un
     * cambio aprobado cuya fecha prevista pasó sin implantarlo.
     *
     * `Propuesto` gasta el violeta de `en_revision`, como un objetivo propuesto y
     * por lo mismo: está hecho y a la espera de que alguien con potestad lo
     * firme. `Implantado` y `Revisado` comparten el verde y los separa el icono:
     * los dos están hechos, y lo que añade el segundo es haber mirado si sirvió.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Propuesto => 'en_revision',
            self::Aprobado => 'en_progreso',
            self::Implantado, self::Revisado => 'implantado',
            self::Descartado => 'no_aplica',
        };
    }
}
