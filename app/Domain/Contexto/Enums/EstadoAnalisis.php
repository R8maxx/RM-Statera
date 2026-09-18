<?php

declare(strict_types=1);

namespace App\Domain\Contexto\Enums;

/**
 * Los tres estados de un análisis del contexto.
 *
 * Es la misma forma que tienen `documento_versiones` y `riesgo_valoraciones`, y
 * por el mismo motivo: lo que hace útil a un análisis del contexto no es lo que
 * dice hoy, es poder poner el de este año al lado del del anterior. Sin un estado
 * que congele, «¿qué ha cambiado en el contexto?» —que es una entrada obligatoria
 * de la cláusula 9.3— se contesta con un encogimiento de hombros.
 *
 * **`Borrador` es la única ventana editable**, y lo impone el trigger. Mientras no
 * tiene número no se ha declarado nada, así que se retoca cuantas veces haga
 * falta.
 *
 * **`Obsoleto` lo pone el sistema y nunca una persona**, igual que
 * `EstadoImplantacion::NoAplica` y que el `obsoleto` de un documento: se escribe
 * solo al aprobarse el análisis siguiente. Por eso no se ofrece como transición
 * desde ninguna parte: no es una decisión que nadie tome, es una consecuencia.
 *
 * **Y no hay `rechazado`**, a diferencia de un documento. Allí hace falta porque
 * la dirección puede tumbar una versión y dejarla tumbada; aquí, un análisis que
 * no convence se sigue retocando en el borrador, que es donde ya estaba. Un estado
 * para «la dirección lo miró y no le gustó» dejaría al análisis anterior vigente y
 * al borrador congelado, que es el peor de los dos mundos.
 */
enum EstadoAnalisis: string
{
    case Borrador = 'borrador';
    case Aprobado = 'aprobado';
    case Obsoleto = 'obsoleto';

    /**
     * A qué estados se puede pasar desde éste.
     *
     * De `Aprobado` sale la única puerta del trigger, y **no la abre nadie a
     * mano**: la usa `AprobarAnalisis` al jubilar al anterior. De `Obsoleto` no se
     * vuelve — decir que el análisis de 2025 vuelve a ser el vigente es reescribir
     * el pasado, igual que devolver una auditoría a `planificada`.
     *
     * @return list<self>
     */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::Borrador => [self::Aprobado],
            self::Aprobado => [self::Obsoleto],
            self::Obsoleto => [],
        };
    }

    public function permite(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }

    /**
     * Si la fila ya está firmada y congelada.
     *
     * Es lo que miran el `CHECK` del número, el de la firma y el de la instantánea,
     * y lo que decide si una cuestión se puede seguir tocando.
     */
    public function esFirmado(): bool
    {
        return $this !== self::Borrador;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Aprobado => 'Vigente',
            self::Obsoleto => 'Sustituido',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Borrador => 'PenLine',
            // El sello, no el visto: lo que hay detrás es una firma de dirección.
            self::Aprobado => 'BadgeCheck',
            self::Obsoleto => 'Archive',
        };
    }

    /**
     * **Ninguno gasta el violeta de `en_revision`**, aunque la tentación esté ahí.
     *
     * Ese token es «hecho y esperando a que alguien con potestad lo confirme», y
     * aquí no hay ese hueco: un análisis está en borrador hasta que se firma, sin
     * paso intermedio. Inventarle uno para poder usar el color sería añadir un
     * estado por motivos de paleta.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Borrador => 'no_iniciado',
            self::Aprobado => 'implantado',
            self::Obsoleto => 'no_aplica',
        };
    }
}
